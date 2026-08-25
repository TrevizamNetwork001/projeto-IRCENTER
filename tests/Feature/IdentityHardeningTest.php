<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserMfaCredential;
use App\Services\Identity\MfaManager;
use App\Services\Identity\RecentPasswordConfirmation;
use App\Services\Identity\SessionRevoker;
use App\Services\Identity\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class IdentityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_totp_enrollment_encrypts_secret_and_rejects_replay(): void
    {
        $user = User::factory()->create();
        $manager = app(MfaManager::class);
        $totp = app(TotpService::class);
        $time = 1787659200;

        $enrollment = $manager->beginEnrollment($user);
        $credential = UserMfaCredential::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertNotSame($enrollment['secret'], $credential->pending_secret);
        $this->assertSame($enrollment['secret'], Crypt::decryptString($credential->pending_secret));

        $codes = $manager->confirmEnrollment(
            $user,
            $totp->codeAt($enrollment['secret'], intdiv($time, 30)),
            $time,
        );
        $this->assertCount(8, $codes);
        $this->assertStringNotContainsString($enrollment['secret'], (string) $credential->fresh()->secret);

        $nextTime = $time + 30;
        $code = $totp->codeAt($enrollment['secret'], intdiv($nextTime, 30));
        $this->assertTrue($manager->verifyTotp($user->fresh(), $code, $nextTime));
        $this->assertFalse($manager->verifyTotp($user->fresh(), $code, $nextTime));
    }

    public function test_recovery_code_is_one_time_and_hashed(): void
    {
        $user = User::factory()->create();
        $manager = app(MfaManager::class);
        $totp = app(TotpService::class);
        $time = 1787659200;
        $enrollment = $manager->beginEnrollment($user);
        $codes = $manager->confirmEnrollment($user, $totp->codeAt($enrollment['secret'], intdiv($time, 30)), $time);

        $credential = UserMfaCredential::query()->where('user_id', $user->id)->firstOrFail();
        $stored = json_encode($credential->fresh()->recovery_codes, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($codes[0], $stored);
        $this->assertTrue($manager->useRecoveryCode($user->fresh(), $codes[0]));
        $this->assertFalse($manager->useRecoveryCode($user->fresh(), $codes[0]));
    }

    public function test_sensitive_mfa_actions_require_recent_password_confirmation(): void
    {
        $user = User::factory()->create();
        $this->expectException(LogicException::class);
        app(MfaManager::class)->disable($user, false);
    }

    public function test_recent_password_confirmation_expires(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);
        $session = app('session.store');
        $service = app(RecentPasswordConfirmation::class);

        $this->assertFalse($service->confirm($user, $session, 'wrong-password'));
        $this->assertTrue($service->confirm($user, $session, 'correct-password'));
        $confirmedAt = $session->get(RecentPasswordConfirmation::SESSION_KEY);
        $this->assertTrue($service->isValid($session, $confirmedAt + 899, 900));
        $this->assertFalse($service->isValid($session, $confirmedAt + 900, 900));
    }

    public function test_user_can_revoke_other_sessions_without_exposing_ids(): void
    {
        $user = User::factory()->create();
        foreach (['current', 'other-one', 'other-two'] as $id) {
            DB::table('sessions')->insert([
                'id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time(),
            ]);
        }

        $this->assertSame(2, app(SessionRevoker::class)->revokeOthers($user, 'current'));
        $this->assertDatabaseHas('sessions', ['id' => 'current']);
        $this->assertDatabaseCount('sessions', 1);
        $this->assertTrue(AuditLog::query()->where('action', 'identity.sessions.others_revoked')->exists());
    }

    public function test_mfa_user_is_not_authenticated_before_valid_challenge(): void
    {
        $user = User::factory()->create(['password' => 'password', 'active' => true]);
        $manager = app(MfaManager::class);
        $totp = app(TotpService::class);
        $enrollment = $manager->beginEnrollment($user);
        $manager->confirmEnrollment(
            $user,
            $totp->codeAt($enrollment['secret'], intdiv(time(), 30)),
        );

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('mfa.challenge'));
        $this->assertGuest();
        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $this->post(route('mfa.challenge.store'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post(route('mfa.challenge.store'), [
            // Enrollment consumed the current timestep; use the accepted next
            // window to prove replay resistance and successful login together.
            'code' => $totp->codeAt($enrollment['secret'], intdiv(time(), 30) + 1),
        ])->assertRedirect(route('dashboard'));
        $this->app['auth']->forgetGuards();
        $this->assertAuthenticatedAs($user);
    }

    public function test_recovery_code_completes_login_only_once(): void
    {
        $user = User::factory()->create(['password' => 'password', 'active' => true]);
        $manager = app(MfaManager::class);
        $totp = app(TotpService::class);
        $enrollment = $manager->beginEnrollment($user);
        $codes = $manager->confirmEnrollment(
            $user,
            $totp->codeAt($enrollment['secret'], intdiv(time(), 30)),
        );

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
        $this->post(route('mfa.challenge.store'), ['code' => $codes[0]])->assertRedirect(route('dashboard'));
        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
        $this->post(route('mfa.challenge.store'), ['code' => $codes[0]])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_security_actions_require_recent_password(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $this->actingAs($user)->get(route('mfa.enroll'))
            ->assertRedirect(route('profile.security'));

        $this->post(route('security.password.confirm'), ['password' => 'password'])
            ->assertRedirect(route('profile.security'));
        $this->get(route('mfa.enroll'))->assertOk();
    }

    public function test_inactive_user_cannot_start_mfa_login(): void
    {
        $user = User::factory()->create(['password' => 'password', 'active' => false]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertFalse(session()->has('auth.mfa_pending_user_id'));
    }

    public function test_mfa_challenge_is_rate_limited(): void
    {
        $user = User::factory()->create(['password' => 'password', 'active' => true]);
        $manager = app(MfaManager::class);
        $totp = app(TotpService::class);
        $enrollment = $manager->beginEnrollment($user);
        $manager->confirmEnrollment($user, $totp->codeAt($enrollment['secret'], intdiv(time(), 30)));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('mfa.challenge.store'), ['code' => '000000']);
        }
        $this->post(route('mfa.challenge.store'), ['code' => '000000'])->assertTooManyRequests();
        $this->assertGuest();
    }
}
