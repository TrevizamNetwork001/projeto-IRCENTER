@extends('scheduling.public.layout')
@section('title','Reagendar — Agenda IRCENTER')
@section('content')
<section class="booking-shell">
 <aside class="booking-service"><span class="eyebrow">Reagendamento</span><h1>{{ $appointment->eventType->name }}</h1><dl><div><dt>Participante</dt><dd>{{ $appointment->guest_name }}</dd></div><div><dt>Horário atual</dt><dd>{{ $appointment->scheduled_start_at->setTimezone($appointment->timezone)->format('d/m/Y H:i') }}</dd></div><div><dt>Timezone atual</dt><dd>{{ $appointment->timezone }}</dd></div></dl></aside>
 <div class="booking-flow"><h2>Escolha o novo horário</h2><div class="calendar-toolbar"><button type="button" class="button" id="month-prev" aria-label="Mês anterior">←</button><strong id="month-label" aria-live="polite"></strong><button type="button" class="button" id="month-next" aria-label="Próximo mês">→</button></div><div id="booking-calendar" class="public-calendar" aria-label="Calendário de disponibilidade" aria-busy="true"><p>Carregando calendário…</p></div><label for="booking-timezone">Timezone</label><select id="booking-timezone"><option selected>{{ $timezone }}</option><option>UTC</option><option>America/New_York</option></select><h3>Horários disponíveis</h3><div id="booking-slots" class="booking-slots" aria-live="polite"><p class="empty-state">Selecione uma data disponível.</p></div><form method="post" id="booking-form" hidden>@csrf<input type="hidden" name="token" value="{{ $token }}"><input type="hidden" name="start" id="selected-start"><input type="hidden" name="timezone" id="selected-timezone" value="{{ $timezone }}">@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif<button class="button button-primary">Confirmar novo horário</button></form></div>
</section>
@endsection
@push('scripts')
@include('scheduling.public.calendar-script',['calendarUrl'=>route('scheduling.public.reschedule.calendar',$appointment),'availabilityUrl'=>route('scheduling.public.reschedule.availability',$appointment),'token'=>$token])
@endpush
