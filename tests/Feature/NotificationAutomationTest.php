<?php

namespace Tests\Feature;

use App\Models\AutomationRun;
use App\Models\Client;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_synchronizes_active_users(): void
    {
        $firstUser = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
            'must_change_password' => false,
        ]);

        $secondUser = User::factory()->create([
            'role' => User::ROLE_OPERATOR,
            'active' => true,
            'must_change_password' => false,
        ]);

        Client::factory()->create([
            'active' => true,
        ]);

        $this->artisan(
            'ircenter:sync-notifications',
            ['--trigger' => 'manual']
        )->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $firstUser->id,
            'unique_key' => 'clients-without-asn',
            'read_at' => null,
            'resolved_at' => null,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $secondUser->id,
            'unique_key' => 'clients-without-asn',
            'read_at' => null,
            'resolved_at' => null,
        ]);

        $this->assertDatabaseHas('automation_runs', [
            'automation' => 'sync-operational-notifications',
            'trigger' => 'manual',
            'status' => AutomationRun::STATUS_COMPLETED,
            'processed_items' => 2,
        ]);
    }

    public function test_command_ignores_inactive_users(): void
    {
        $inactiveUser = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => false,
            'must_change_password' => false,
        ]);

        Client::factory()->create([
            'active' => true,
        ]);

        $this->artisan('ircenter:sync-notifications')
            ->assertSuccessful();

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $inactiveUser->id,
        ]);

        $this->assertDatabaseHas('automation_runs', [
            'status' => AutomationRun::STATUS_COMPLETED,
            'processed_items' => 0,
            'result_items' => 0,
        ]);
    }

    public function test_command_resolves_notification_after_issue_ends(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
            'must_change_password' => false,
        ]);

        $client = Client::factory()->create([
            'active' => true,
        ]);

        $this->artisan('ircenter:sync-notifications')
            ->assertSuccessful();

        $notification = Notification::query()
            ->where('user_id', $user->id)
            ->where('unique_key', 'clients-without-asn')
            ->firstOrFail();

        $client->update([
            'active' => false,
        ]);

        $this->artisan('ircenter:sync-notifications')
            ->assertSuccessful();

        $this->assertNotNull(
            $notification->fresh()->resolved_at
        );
    }

    public function test_invalid_trigger_is_rejected(): void
    {
        $this->artisan(
            'ircenter:sync-notifications',
            ['--trigger' => 'invalid']
        )->assertExitCode(Command::INVALID);
    }

    public function test_scheduler_contains_notification_automation(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain(
                'ircenter:sync-notifications'
            )
            ->assertSuccessful();
    }
}
