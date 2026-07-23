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
}
