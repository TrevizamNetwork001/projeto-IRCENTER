@extends('scheduling.public.layout')
@section('title',($appointment->status==='cancelled'?'Agendamento cancelado':'Agendamento confirmado').' — IRCENTER')
@section('content')
@php
 $local = $appointment->scheduled_start_at->setTimezone($appointment->timezone);
 $cancelled = $appointment->status==='cancelled';
@endphp
@if($cancelled)
<article class="confirmation-card confirmation-card-standalone">
 <span class="confirmation-icon is-cancelled" aria-hidden="true"><x-icon name="close" size="26"/></span>
 <h1>Agendamento cancelado</h1>
 <p>Este agendamento foi cancelado.</p>
 <dl class="detail-list confirmation-participant">
  <div><dt>Participante</dt><dd>{{ $appointment->guest_name }}</dd></div>
 </dl>
</article>
@else
<div class="confirmation-shell">
 <div class="confirmation-intro">
  <h1>Obrigado pelo seu agendamento!</h1>
  <p>Sua reunião foi confirmada com sucesso. Enviamos os detalhes para o seu e-mail.</p>
  <ul class="confirmation-badges">
   <li><x-icon name="registry" size="18"/> Organização</li>
   <li><x-icon name="shield" size="18"/> Segurança</li>
   <li><x-icon name="activity" size="18"/> Eficiência</li>
  </ul>
 </div>

 <article class="confirmation-card">
  <span class="confirmation-icon" aria-hidden="true"><x-icon name="check" size="26"/></span>
  <h2>Agendamento confirmado</h2>
  <p>Confira os detalhes da sua reunião:</p>

  <div class="confirmation-grid">
   <div>
    <span class="confirmation-grid-icon" aria-hidden="true"><x-icon name="calendar" size="16"/></span>
    <span class="confirmation-grid-label">Data</span>
    <strong>{{ $local->translatedFormat('d \d\e F \d\e Y') }}</strong>
    <span class="confirmation-grid-sub">{{ $local->translatedFormat('l') }}</span>
   </div>
   <div>
    <span class="confirmation-grid-icon" aria-hidden="true"><x-icon name="clock" size="16"/></span>
    <span class="confirmation-grid-label">Horário</span>
    <strong>{{ $local->format('H:i') }} – {{ $local->copy()->addMinutes($appointment->eventType->duration_minutes)->format('H:i') }}</strong>
    <span class="confirmation-grid-sub">({{ $appointment->eventType->duration_minutes }} minutos)</span>
   </div>
   <div>
    <span class="confirmation-grid-icon" aria-hidden="true"><x-icon name="video" size="16"/></span>
    <span class="confirmation-grid-label">Serviço</span>
    <strong>{{ $appointment->eventType->name }}</strong>
    <span class="confirmation-grid-sub">{{ $appointment->eventType->location_value ?: 'Online' }}</span>
   </div>
   <div>
    <span class="confirmation-grid-icon" aria-hidden="true"><x-icon name="globe" size="16"/></span>
    <span class="confirmation-grid-label">Fuso horário</span>
    <strong>{{ $appointment->timezone }}</strong>
    <span class="confirmation-grid-sub">(Horário de Brasília)</span>
   </div>
  </div>

  <div class="confirmation-actions">
   <a class="button button-primary" href="{{ route('scheduling.public.ics',['appointment'=>$appointment,'token'=>$token]) }}"><x-icon name="calendar" size="16"/> Adicionar ao calendário (.ics)</a>
   <a class="button" href="{{ route('scheduling.public.show',$appointment->eventType) }}">Agendar uma nova reunião →</a>
  </div>

  @if($rescheduleUrl || $cancelUrl)
  <p class="confirmation-secondary-links">
   @if($rescheduleUrl)<a href="{{ $rescheduleUrl }}">Reagendar</a>@endif
   @if($rescheduleUrl && $cancelUrl)<span aria-hidden="true">·</span>@endif
   @if($cancelUrl)<a href="{{ $cancelUrl }}">Cancelar agendamento</a>@endif
  </p>
  @endif

  <p class="confirmation-participant-note">Agendado por {{ $appointment->guest_name }}</p>
 </article>
</div>
<p class="confirmation-footer">© {{ date('Y') }} IRCENTER · Internet Resource Center</p>
@endif
@endsection
