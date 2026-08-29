<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Scheduling\Models\Appointment;
use App\Modules\Scheduling\Models\EventType;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class SlotGenerator
{
    /** @return array<int,array{start:string,end:string,label:string}> */
    public function generate(EventType $eventType, string $date, string $displayTimezone, ?CarbonImmutable $now = null): array
    {
        $this->assertTimezone($displayTimezone);
        $localDate = CarbonImmutable::createFromFormat('!Y-m-d', $date, $displayTimezone);
        if (! $localDate || $localDate->format('Y-m-d') !== $date) throw new InvalidArgumentException('Data inválida.');
        $now = ($now ?: CarbonImmutable::now('UTC'))->utc();
        $lastDate = $now->setTimezone($displayTimezone)->addDays(min($eventType->maximum_days_ahead, (int) config('scheduling.max_booking_horizon')))->endOfDay();
        if ($localDate->endOfDay()->isBefore($now->setTimezone($displayTimezone)) || $localDate->startOfDay()->isAfter($lastDate)) return [];

        $exceptions = $eventType->exceptions()->whereDate('date', $date)->get();
        $fullDayUnavailable = $exceptions->contains(fn($e) => $e->type === 'unavailable' && $e->start_time === null);
        $override = $exceptions->where('type', 'available_override');
        if ($fullDayUnavailable && $override->isEmpty()) return [];
        $windows = $override->isNotEmpty() ? $override : $eventType->rules()->where('active', true)->where('day_of_week', $localDate->dayOfWeekIso)->get();
        $rangeStart = $localDate->startOfDay()->utc(); $rangeEnd = $localDate->endOfDay()->utc();
        $appointments = Appointment::query()->where('event_type_id', $eventType->id)->whereIn('status',['scheduled','confirmed'])
            ->where('scheduled_start_at','<',$rangeEnd)->where('scheduled_end_at','>',$rangeStart)->get();
        $slots=[]; $interval=max(5,(int)$eventType->slot_interval_minutes); $duration=(int)$eventType->duration_minutes;
        foreach ($windows as $window) {
            $zone = $override->isNotEmpty() ? $displayTimezone : $window->timezone;
            $start=CarbonImmutable::parse($date.' '.substr($window->start_time,0,5),$zone); $end=CarbonImmutable::parse($date.' '.substr($window->end_time,0,5),$zone);
            for ($slot=$start; $slot->addMinutes($duration)->lte($end); $slot=$slot->addMinutes($interval)) {
                $slotEnd=$slot->addMinutes($duration); $blockedStart=$slot->subMinutes((int)$eventType->buffer_before_minutes)->utc(); $blockedEnd=$slotEnd->addMinutes((int)$eventType->buffer_after_minutes)->utc();
                if ($blockedStart->lt($now->addMinutes((int)$eventType->minimum_notice_minutes))) continue;
                if ($this->blockedByException($exceptions,$slot,$slotEnd,$displayTimezone)) continue;
                if ($appointments->contains(fn($a) => $a->scheduled_start_at->subMinutes((int)$eventType->buffer_before_minutes)->lt($blockedEnd) && $a->scheduled_end_at->addMinutes((int)$eventType->buffer_after_minutes)->gt($blockedStart))) continue;
                $shown=$slot->setTimezone($displayTimezone); $shownEnd=$slotEnd->setTimezone($displayTimezone);
                $slots[]=['start'=>$shown->toIso8601String(),'end'=>$shownEnd->toIso8601String(),'label'=>$shown->format('H:i')];
            }
        }
        return collect($slots)->unique('start')->sortBy('start')->values()->all();
    }

    private function blockedByException(Collection $exceptions, CarbonImmutable $start, CarbonImmutable $end, string $timezone): bool
    {
        return $exceptions->where('type','unavailable')->contains(function($e) use($start,$end,$timezone): bool {
            if ($e->start_time === null) return true;
            $from=CarbonImmutable::parse($e->date->format('Y-m-d').' '.substr($e->start_time,0,5),$timezone);
            $to=CarbonImmutable::parse($e->date->format('Y-m-d').' '.substr($e->end_time,0,5),$timezone);
            return $start->lt($to) && $end->gt($from);
        });
    }
    public function assertTimezone(string $timezone): void { if (!in_array($timezone,DateTimeZone::listIdentifiers(),true)) throw new InvalidArgumentException('Timezone inválido.'); }
}
