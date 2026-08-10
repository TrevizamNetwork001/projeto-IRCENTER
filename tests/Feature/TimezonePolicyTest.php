<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class TimezonePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_technical_and_business_timezones_are_explicit(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame(
            'America/Sao_Paulo',
            config('business.timezone')
        );
        $this->assertSame(
            'America/Sao_Paulo',
            app(BusinessClock::class)->timezone()->getName()
        );
    }

    public function test_technical_eloquent_timestamps_remain_utc(): void
    {
        CarbonImmutable::setTestNow('2026-08-20T15:00:00Z');
        $user = User::factory()->create();

        $this->assertSame('UTC', $user->created_at->timezoneName);
        $this->assertSame(
            '2026-08-20T15:00:00+00:00',
            $user->created_at->toIso8601String()
        );
        $this->assertSame(
            '2026-08-20 12:00',
            app(BusinessClock::class)
                ->toBusinessTimezone($user->created_at)
                ->format('Y-m-d H:i')
        );
    }

    public function test_unsupported_business_timezone_fails_closed(): void
    {
        config()->set('business.timezone', 'UTC');
        $this->app->forgetInstance(BusinessClock::class);

        $this->expectException(\InvalidArgumentException::class);
        app(BusinessClock::class);
    }

    public function test_current_technical_scheduler_is_explicitly_utc(): void
    {
        $event = collect(Schedule::events())->first(
            fn ($event) => $event->description
                === 'ircenter-sync-operational-notifications'
        );

        $this->assertNotNull($event);
        $this->assertSame('UTC', $event->timezone);
        $this->assertSame('*/5 * * * *', $event->expression);
    }
}
