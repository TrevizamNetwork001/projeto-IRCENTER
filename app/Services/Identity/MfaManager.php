<?php

namespace App\Services\Identity;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserMfaCredential;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;

class MfaManager
{
    public function __construct(private readonly TotpService $totp) {}

    /** @return array{secret: string, uri: string} */
    public function beginEnrollment(User $user): array
    {
        $secret = $this->totp->generateSecret();
        $this->credential($user)->forceFill(['pending_secret' => Crypt::encryptString($secret)])->save();
        $this->audit($user, 'identity.mfa.enrollment_started');

        return ['secret' => $secret, 'uri' => $this->totp->uri($secret, $user->email)];
    }

    /** @return array<int, string> */
    public function confirmEnrollment(User $user, string $code, ?int $timestamp = null): array
    {
        $credential = $this->credential($user);
        if (! is_string($credential->pending_secret)) {
            throw new LogicException('Enrollment MFA não iniciado.');
        }

        $secret = Crypt::decryptString($credential->pending_secret);
        $step = $this->totp->verify($secret, $code, $timestamp);
        if ($step === null) {
            throw new LogicException('Código MFA inválido.');
        }

        [$plain, $hashed] = $this->newRecoveryCodes();
        $credential->forceFill([
            'secret' => Crypt::encryptString($secret),
            'pending_secret' => null,
            'recovery_codes' => $hashed,
            'confirmed_at' => now(),
            'last_used_step' => $step,
        ])->save();
        $this->audit($user, 'identity.mfa.enabled');

        return $plain;
    }

    public function verifyTotp(User $user, string $code, ?int $timestamp = null): bool
    {
        $credential = $this->credential($user);
        if (! is_string($credential->secret) || $credential->confirmed_at === null) {
            return false;
        }

        $step = $this->totp->verify(Crypt::decryptString($credential->secret), $code, $timestamp);
        if ($step === null || $step <= (int) ($credential->last_used_step ?? -1)) {
            return false;
        }

        $credential->forceFill(['last_used_step' => $step])->save();

        return true;
    }

    public function isEnabled(User $user): bool
    {
        return UserMfaCredential::query()
            ->where('user_id', $user->id)
            ->whereNotNull('confirmed_at')
            ->exists();
    }

    public function useRecoveryCode(User $user, string $code): bool
    {
        $credential = $this->credential($user);
        $hashes = $credential->recovery_codes;
        if (! is_array($hashes)) {
            return false;
        }

        foreach ($hashes as $index => $hash) {
            if (is_string($hash) && Hash::check($code, $hash)) {
                unset($hashes[$index]);
                $credential->forceFill(['recovery_codes' => array_values($hashes)])->save();
                $this->audit($user, 'identity.mfa.recovery_code_used');

                return true;
            }
        }

        return false;
    }

    /** @return array<int, string> */
    public function regenerateRecoveryCodes(User $user, bool $passwordRecentlyConfirmed): array
    {
        $this->requirePasswordConfirmation($passwordRecentlyConfirmed);
        [$plain, $hashed] = $this->newRecoveryCodes();
        $this->credential($user)->forceFill(['recovery_codes' => $hashed])->save();
        $this->audit($user, 'identity.mfa.recovery_codes_regenerated');

        return $plain;
    }

    public function disable(User $user, bool $passwordRecentlyConfirmed): void
    {
        $this->requirePasswordConfirmation($passwordRecentlyConfirmed);
        $this->credential($user)->forceFill([
            'secret' => null,
            'pending_secret' => null,
            'recovery_codes' => null,
            'confirmed_at' => null,
            'last_used_step' => null,
        ])->save();
        $this->audit($user, 'identity.mfa.disabled');
    }

    /** @return array{0: array<int, string>, 1: array<int, string>} */
    private function newRecoveryCodes(): array
    {
        $plain = [];
        for ($index = 0; $index < 8; $index++) {
            $plain[] = strtoupper(Str::random(5).'-'.Str::random(5));
        }

        return [$plain, array_map(fn (string $code) => Hash::make($code), $plain)];
    }

    private function requirePasswordConfirmation(bool $confirmed): void
    {
        if (! $confirmed) {
            throw new LogicException('Confirmação recente de senha obrigatória.');
        }
    }

    private function credential(User $user): UserMfaCredential
    {
        return UserMfaCredential::query()->firstOrCreate(['user_id' => $user->id]);
    }

    private function audit(User $user, string $action): void
    {
        AuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'resource_type' => 'User',
            'resource_id' => $user->id,
            'resource_label' => 'User #'.$user->id,
            'old_values' => null,
            'new_values' => null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
