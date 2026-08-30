<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_users(): void
    {
        $this->get(route('users.index'))
            ->assertRedirect(route('login'));
    }

    public function test_non_administrator_cannot_access_users(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_OPERATOR,
            'active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_administrator_can_create_user(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Operador Teste',
                'email' => 'operador@example.net',
                'role' => User::ROLE_OPERATOR,
                'password' => 'SenhaForte123',
                'password_confirmation' => 'SenhaForte123',
                'active' => '1',
                'must_change_password' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'operador@example.net',
            'role' => User::ROLE_OPERATOR,
            'active' => true,
            'must_change_password' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'resource_type' => 'User',
            'resource_label' => 'operador@example.net',
        ]);
    }

    public function test_administrator_can_define_user_avatar(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Operador Robô',
                'email' => 'robot@example.net',
                'avatar_key' => 'robot',
                'role' => User::ROLE_OPERATOR,
                'password' => 'SenhaForte123',
                'password_confirmation' => 'SenhaForte123',
                'active' => '1',
                'must_change_password' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'robot@example.net',
            'avatar_key' => 'robot',
        ]);
    }

    public function test_user_can_change_own_avatar(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
            'must_change_password' => false,
            'avatar_key' => null,
        ]);

        $this->actingAs($user)
            ->put(route('profile.avatar.update'), [
                'avatar_key' => 'owl',
            ])
            ->assertRedirect();

        $this->assertSame(
            'owl',
            $user->fresh()->avatar_key
        );

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'avatar_updated',
            'resource_type' => 'User',
            'resource_id' => $user->id,
        ]);
    }

    public function test_invalid_avatar_is_rejected(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->put(route('profile.avatar.update'), [
                'avatar_key' => 'invalid-avatar',
            ])
            ->assertSessionHasErrors('avatar_key');

        $this->assertNull($user->fresh()->avatar_key);
    }

    public function test_authenticated_user_can_access_own_profile(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Meu perfil')
            ->assertSee($user->name);
    }

    public function test_profile_displays_account_timestamps_in_business_timezone(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'must_change_password' => false,
            'last_login_at' => '2026-08-17 18:53:00',
            'password_changed_at' => '2026-08-17 12:18:00',
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('17/08/2026 15:53')
            ->assertSee('17/08/2026 09:18')
            ->assertDontSee('17/08/2026 18:53')
            ->assertDontSee('17/08/2026 12:18');
    }

    public function test_user_can_access_voluntary_password_form(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->get(route('profile.password.edit'))
            ->assertOk()
            ->assertSee('Alterar minha senha');
    }

    public function test_user_can_change_password_from_profile(): void
    {
        $user = User::factory()->create([
            'password' => 'SenhaAtual123',
            'active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->put(route('profile.password.update'), [
                'current_password' => 'SenhaAtual123',
                'password' => 'NovaSenha987',
                'password_confirmation' => 'NovaSenha987',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue(
            Hash::check('NovaSenha987', $user->fresh()->password)
        );

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'password_changed',
            'resource_type' => 'User',
            'resource_id' => $user->id,
        ]);
    }

    public function test_profile_password_requires_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'SenhaAtual123',
            'active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->put(route('profile.password.update'), [
                'current_password' => 'SenhaErrada123',
                'password' => 'NovaSenha987',
                'password_confirmation' => 'NovaSenha987',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(
            Hash::check(
                'SenhaAtual123',
                $user->fresh()->password
            )
        );
    }

    public function test_administrator_cannot_block_self(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('users.toggle-active', $admin))
            ->assertSessionHasErrors('active');

        $this->assertTrue($admin->fresh()->active);
    }

    public function test_administrator_can_change_password_from_edit_form(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'role' => User::ROLE_VIEWER,
                'password' => 'NovaSenha123',
                'active' => '1',
                'must_change_password' => '0',
            ])
            ->assertRedirect();

        $this->assertTrue(
            Hash::check('NovaSenha123', $user->fresh()->password)
        );
    }

    public function test_leaving_password_blank_keeps_current_password(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
        ]);

        $originalPassword = $user->password;

        $this->actingAs($admin)
            ->put(route('users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'role' => User::ROLE_VIEWER,
                'password' => '',
                'active' => '1',
                'must_change_password' => '0',
            ])
            ->assertRedirect();

        $this->assertSame($originalPassword, $user->fresh()->password);
    }

    public function test_administrator_can_reset_password(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('users.reset-password', $user), [
                'password' => 'NovaSenha123',
                'password_confirmation' => 'NovaSenha123',
                'must_change_password' => '1',
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertTrue(
            Hash::check('NovaSenha123', $user->password)
        );

        $this->assertTrue($user->must_change_password);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'password_reset',
            'resource_type' => 'User',
            'resource_id' => $user->id,
        ]);
    }

    public function test_user_with_pending_password_change_is_redirected_after_login(): void
    {
        $user = User::factory()->create([
            'email' => 'temporary@example.net',
            'password' => 'SenhaAtual123',
            'active' => true,
            'must_change_password' => true,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'SenhaAtual123',
        ])->assertRedirect(route('password.change.edit'));
    }

    public function test_pending_password_change_blocks_dashboard(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('password.change.edit'));
    }

    public function test_pending_password_change_allows_logout(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_user_can_complete_mandatory_password_change(): void
    {
        $user = User::factory()->create([
            'email' => 'change@example.net',
            'password' => 'SenhaAtual123',
            'active' => true,
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->put(route('password.change.update'), [
                'current_password' => 'SenhaAtual123',
                'password' => 'NovaSenha456',
                'password_confirmation' => 'NovaSenha456',
            ])
            ->assertRedirect(route('dashboard'));

        $user->refresh();

        $this->assertFalse($user->must_change_password);

        $this->assertTrue(
            Hash::check('NovaSenha456', $user->password)
        );

        $this->assertNotNull($user->password_changed_at);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'password_changed',
            'resource_type' => 'User',
            'resource_id' => $user->id,
        ]);
    }

    public function test_current_password_is_required_to_change_password(): void
    {
        $user = User::factory()->create([
            'password' => 'SenhaAtual123',
            'active' => true,
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->put(route('password.change.update'), [
                'current_password' => 'SenhaIncorreta123',
                'password' => 'NovaSenha456',
                'password_confirmation' => 'NovaSenha456',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(
            $user->fresh()->must_change_password
        );
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'blocked@example.net',
            'password' => 'password',
            'active' => false,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_user_is_logged_out_after_deactivation(): void
    {
        $user = User::factory()->create([
            'active' => true,
        ]);

        $this->actingAs($user);

        $user->update(['active' => false]);

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
