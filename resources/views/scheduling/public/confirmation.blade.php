@extends('scheduling.public.layout')
@section('title',($appointment->status==='cancelled'?'Agendamento cancelado':'Agendamento confirmado').' — IRCENTER')
@section('content')
@php
 $local = $appointment->scheduled_start_at->setTimezone($appointment->timezone);
 $cancelled = $appointment->status==='cancelled';
@endphp
<article class="confirmation-card">
 <span class="confirmation-icon {{ $cancelled ? 'is-cancelled' : '' }}" aria-hidden="true"><x-icon :name="$cancelled ? 'close' : 'check'" size="26"/></span>
 <h1>{{ $cancelled ? 'Agendamento cancelado' : 'Agendamento confirmado!' }}</h1>
 <p>{{ $cancelled ? 'Este agendamento foi cancelado.' : 'Sua reunião foi agendada com sucesso.' }}</p>

 @unless($cancelled)
 <div class="confirmation-summary">
  <div class="confirmation-summary-row">
   <span class="confirmation-summary-icon" aria-hidden="true"><x-icon name="users" size="18"/></span>
   <div><span class="confirmation-summary-label">Reunião</span><strong>{{ $appointment->eventType->name }}</strong></div>
  </div>
  <div class="confirmation-summary-row">
   <span class="confirmation-summary-icon" aria-hidden="true"><x-icon name="calendar" size="18"/></span>
   <div><span class="confirmation-summary-label">Data e horário</span><strong>{{ $local->translatedFormat('d \d\e F \d\e Y') }} · {{ $local->format('H:i') }} – {{ $local->copy()->addMinutes($appointment->eventType->duration_minutes)->format('H:i') }}</strong></div>
  </div>
  <div class="confirmation-summary-row">
   <span class="confirmation-summary-icon" aria-hidden="true"><x-icon name="monitor" size="18"/></span>
   <div><span class="confirmation-summary-label">Local</span><strong>{{ $appointment->eventType->location_value ?: 'Online' }}</strong></div>
  </div>
  <div class="confirmation-summary-row">
   <span class="confirmation-summary-icon" aria-hidden="true"><x-icon name="globe" size="18"/></span>
   <div><span class="confirmation-summary-label">Fuso horário</span><strong>Horário de Brasília ({{ $appointment->timezone }})</strong></div>
  </div>
  <div class="confirmation-summary-row">
   <span class="confirmation-summary-icon" aria-hidden="true"><x-icon name="mail" size="18"/></span>
   <div><span class="confirmation-summary-label">Participante</span><strong>{{ $appointment->guest_name }}</strong></div>
  </div>
 </div>
 @else
 <dl class="detail-list confirmation-participant">
  <div><dt>Participante</dt><dd>{{ $appointment->guest_name }}</dd></div>
 </dl>
 @endunless

 @unless($cancelled)
 <div class="confirmation-actions">
  <a class="button button-primary" href="{{ route('scheduling.public.ics',$appointment) }}"><x-icon name="calendar" size="16"/> Adicionar ao calendário (.ics)</a>
  @if($rescheduleUrl)<a class="button" href="{{ $rescheduleUrl }}">Reagendar</a>@endif
  @if($cancelUrl)<a class="button" href="{{ $cancelUrl }}">Cancelar</a>@endif
 </div>
 <p class="confirmation-secondary-action">
  <a href="{{ route('scheduling.public.show',$appointment->eventType) }}">Voltar para o início</a>
 </p>
 @endunless
</article>
@endsection
