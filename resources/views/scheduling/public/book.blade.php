@extends('scheduling.public.layout')
@section('title',$eventType->name.' — Agenda IRCENTER')
@section('content')
<section class="booking-shell">
 <aside class="booking-service"><span class="eyebrow">Agendamento</span><h1>{{ $eventType->name }}</h1><p>{{ $eventType->description }}</p><dl><div><dt>Duração</dt><dd>{{ $eventType->duration_minutes }} min</dd></div><div><dt>Local</dt><dd>{{ $eventType->location_value ?: ucfirst($eventType->location_type) }}</dd></div></dl></aside>
 <div class="booking-flow">
  <h2>Escolha data e horário</h2>
  <div class="calendar-toolbar"><button type="button" class="button" id="month-prev" aria-label="Mês anterior">←</button><strong id="month-label" aria-live="polite"></strong><button type="button" class="button" id="month-next" aria-label="Próximo mês">→</button></div>
  <div id="booking-calendar" class="public-calendar" aria-label="Calendário de disponibilidade" aria-busy="true"><p>Carregando calendário…</p></div>
  <p class="calendar-legend"><span class="legend-available">Disponível</span><span class="legend-unavailable">Indisponível</span><span class="legend-limited">Fora do período/antecedência</span></p>
  <label for="booking-timezone">Timezone</label><select id="booking-timezone"><option selected>{{ $timezone }}</option><option>UTC</option><option>America/New_York</option></select>
  <h3 id="slots-title">Horários disponíveis</h3><div id="booking-slots" class="booking-slots" aria-live="polite"><p class="empty-state">Selecione uma data disponível.</p></div>
  <form method="post" action="{{ route('scheduling.public.store',$eventType) }}" id="booking-form" hidden>@csrf<input type="hidden" name="start" id="selected-start"><input type="hidden" name="timezone" id="selected-timezone" value="{{ $timezone }}"><input type="hidden" name="form_started_at" value="{{ time() }}"><div class="honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div><h2>Seus dados</h2><label>Nome<input name="guest_name" required maxlength="120" value="{{ old('guest_name') }}"></label><label>E-mail<input type="email" name="guest_email" required maxlength="255" value="{{ old('guest_email') }}"></label><div class="form-pair"><label>Telefone (opcional)<input name="guest_phone" maxlength="40"></label><label>Empresa (opcional)<input name="guest_company" maxlength="120"></label></div><label>Observações (opcional)<textarea name="notes" maxlength="2000"></textarea></label>@if($errors->any())<div class="alert alert-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif<button class="button button-primary" type="submit">Confirmar agendamento</button></form>
 </div>
</section>
@endsection
@push('scripts')
@include('scheduling.public.calendar-script',['calendarUrl'=>route('scheduling.public.calendar',$eventType),'availabilityUrl'=>route('scheduling.public.availability',$eventType),'token'=>null])
@endpush
