@extends('scheduling.public.layout')
@section('title',$eventType->name.' — Agenda IRCENTER')
@section('content')
<section class="booking-shell" data-booking-stage="{{ $errors->any() ? 'form' : 'calendar' }}">
 <aside class="booking-service"><span class="eyebrow">Agendamento IRCENTER</span><h1>{{ $eventType->name }}</h1>@if($eventType->description)<p>{{ $eventType->description }}</p>@endif
  <dl><div><dt>Duração</dt><dd>{{ $eventType->duration_minutes }} minutos</dd></div>@if($eventType->location_value)<div><dt>Local</dt><dd>{{ $eventType->location_value }}</dd></div>@endif<div><dt>Timezone</dt><dd id="context-timezone">{{ $timezone }}</dd></div><div id="context-selection" hidden><dt>Data e horário</dt><dd id="context-datetime"></dd></div></dl>
 </aside>
 <div class="booking-picker" id="booking-picker">
  <div class="booking-calendar-panel"><h2>Selecione uma data</h2><div class="calendar-toolbar"><button type="button" class="calendar-nav" id="month-prev" aria-label="Mês anterior">←</button><strong id="month-label" aria-live="polite"></strong><button type="button" class="calendar-nav" id="month-next" aria-label="Próximo mês">→</button></div><div class="calendar-weekdays" aria-hidden="true"><span>dom</span><span>seg</span><span>ter</span><span>qua</span><span>qui</span><span>sex</span><span>sáb</span></div><div id="booking-calendar" class="public-calendar" aria-label="Calendário de disponibilidade" aria-busy="true"><p>Carregando calendário…</p></div><label class="timezone-control" for="booking-timezone">Timezone<select id="booking-timezone"><option selected>{{ $timezone }}</option><option>UTC</option><option>America/New_York</option></select></label></div>
  <aside class="booking-times" id="booking-times" aria-labelledby="slots-title" hidden><h2 id="slots-title">Horários disponíveis</h2><p id="selected-date-label" class="selected-date-label"></p><div id="booking-slots" class="booking-slots" aria-live="polite"></div></aside>
 </div>
 <div class="booking-form-panel" id="booking-form-panel" @if(!$errors->any()) hidden @endif><button type="button" class="back-button" id="booking-back">← Voltar</button><h2>Informe seus dados</h2><p>Você receberá os detalhes do agendamento no e-mail informado.</p>
  <form method="post" action="{{ route('scheduling.public.store',$eventType) }}" id="booking-form">@csrf<input type="hidden" name="start" id="selected-start" value="{{ old('start') }}"><input type="hidden" name="timezone" id="selected-timezone" value="{{ old('timezone',$timezone) }}"><input type="hidden" name="form_started_at" value="{{ time() }}"><div class="honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
   <label for="guest_name">Nome <span aria-hidden="true">*</span><input id="guest_name" name="guest_name" required maxlength="120" autocomplete="name" value="{{ old('guest_name') }}" @error('guest_name') aria-invalid="true" aria-describedby="guest_name-error" @enderror></label>@error('guest_name')<p class="field-error" id="guest_name-error">{{ $message }}</p>@enderror
   <label for="guest_email">E-mail <span aria-hidden="true">*</span><input id="guest_email" type="email" name="guest_email" required maxlength="255" autocomplete="email" value="{{ old('guest_email') }}" @error('guest_email') aria-invalid="true" aria-describedby="guest_email-error" @enderror></label>@error('guest_email')<p class="field-error" id="guest_email-error">{{ $message }}</p>@enderror
   <label for="guest_phone">Telefone <span>(opcional)</span><input id="guest_phone" type="tel" name="guest_phone" maxlength="40" autocomplete="tel" value="{{ old('guest_phone') }}"></label><label for="notes">Motivo ou observação <span>(opcional)</span><textarea id="notes" name="notes" maxlength="2000">{{ old('notes') }}</textarea></label>
   @if($errors->hasAny(['start','timezone','guest_phone','notes','website','form_started_at']))<div class="alert alert-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif<button class="button button-primary booking-confirm" type="submit">Confirmar agendamento</button>
  </form>
 </div>
</section>
@endsection
@include('scheduling.public.calendar-script',['calendarUrl'=>route('scheduling.public.calendar',$eventType),'availabilityUrl'=>route('scheduling.public.availability',$eventType),'token'=>null,'formMode'=>true])
