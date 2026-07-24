<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_notifications(): void
    {
        $this->get(route('notifications.index'))
            ->assertRedirect(route('login'));
    }

    public function test_operational_notification_is_generated_for_user(): void
    {
        $viewer = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
            'must_change_password' => false,
        ]);

        Client::factory()->create([
            'active' => true,
        ]);

        $this->artisan('ircenter:sync-notifications')
            ->assertSuccessful();

        $this->actingAs($viewer)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Clientes sem ASN ativo')
            ->assertSee('1 cliente ativo está sem ASN ativo vinculado.');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $viewer->id,
            'unique_key' => 'clients-without-asn',
            'priority' => Notification::PRIORITY_WARNING,
            'read_at' => null,
            'resolved_at' => null,
        ]);
    }

    public function test_viewer_does_not_receive_admin_notification(): void
    {
        $viewer = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
            'must_change_password' => false,
        ]);

        User::factory()->create([
            'active' => false,
        ]);

        $this->actingAs($viewer)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertDontSee('Usuários bloqueados');

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $viewer->id,
            'unique_key' => 'blocked-users',
        ]);
    }

    public function test_user_can_mark_own_notification_as_read(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_OPERATOR,
            'active' => true,
            'must_change_password' => false,
        ]);

        $notification = Notification::create([
            'user_id' => $user->id,
            'unique_key' => 'test-notification',
            'type' => 'operational',
            'priority' => Notification::PRIORITY_INFO,
            'title' => 'Notificação de teste',
            'message' => 'Mensagem de teste.',
        ]);

        $this->actingAs($user)
            ->patch(route('notifications.read', $notification))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_OPERATOR,
            'active' => true,
            'must_change_password' => false,
        ]);

        $otherUser = User::factory()->create([
            'role' => User::ROLE_OPERATOR,
            'active' => true,
            'must_change_password' => false,
        ]);

        $notification = Notification::create([
            'user_id' => $otherUser->id,
            'unique_key' => 'private-notification',
            'type' => 'operational',
            'priority' => Notification::PRIORITY_INFO,
            'title' => 'Notificação privada',
            'message' => 'Mensagem privada.',
        ]);

        $this->actingAs($user)
            ->patch(route('notifications.read', $notification))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_OPERATOR,
            'active' => true,
            'must_change_password' => false,
        ]);

        Notification::create([
            'user_id' => $user->id,
            'unique_key' => 'first-notification',
            'type' => 'operational',
            'priority' => Notification::PRIORITY_INFO,
            'title' => 'Primeira notificação',
            'message' => 'Primeira mensagem.',
        ]);

        Notification::create([
            'user_id' => $user->id,
            'unique_key' => 'second-notification',
            'type' => 'operational',
            'priority' => Notification::PRIORITY_WARNING,
            'title' => 'Segunda notificação',
            'message' => 'Segunda mensagem.',
        ]);

        $this->actingAs($user)
            ->patch(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(
            0,
            Notification::query()
                ->where('user_id', $user->id)
                ->whereNull('read_at')
                ->count()
        );
    }
}
