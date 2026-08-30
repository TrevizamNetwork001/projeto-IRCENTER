@extends('scheduling.public.layout')
@section('title',$eventType->name.' — Agenda IRCENTER')
@section('content')
<section class="booking-shell" id="booking-shell" data-booking-stage="{{ $errors->any() ? 'form' : 'calendar' }}">
 <aside class="booking-service">
  <div class="booking-service-row booking-service-heading">
   <span class="booking-service-row-icon"><x-icon name="video" size="18"/></span>
   <div>
    <h1>{{ $eventType->name }}</h1>
    <p class="booking-service-lead">{{ $eventType->description ?: 'Reunião com nossa equipe' }}</p>
   </div>
  </div>
  <div class="booking-service-row">
   <span class="booking-service-row-icon"><x-icon name="clock" size="16"/></span>
   <div><span class="booking-service-row-label">Duração</span><strong>{{ $eventType->duration_minutes }} minutos</strong></div>
  </div>
  <div class="booking-service-row">
   <span class="booking-service-row-icon"><x-icon name="monitor" size="16"/></span>
   <div><span class="booking-service-row-label">Formato</span><strong>{{ $eventType->location_value ?: 'Online' }}</strong></div>
  </div>
  <div class="booking-service-row booking-service-context" id="context-selection" hidden>
   <span class="booking-service-row-icon"><x-icon name="calendar" size="16"/></span>
   <div>
    <span class="booking-service-row-label">Data e horário</span>
    <strong id="context-date"></strong>
    <strong id="context-time"></strong>
    <span class="booking-service-context-zone">Horário de Brasília ({{ $timezone }})</span>
   </div>
  </div>
  <p class="booking-service-note">Converse com nossos especialistas e tire suas dúvidas.</p>
  <p class="booking-service-trust"><x-icon name="shield" size="16"/> Seus dados estão seguros<br><span>Este agendamento é protegido e confidencial.</span></p>
 </aside>

 <div class="booking-flow">
  <ol class="booking-stepper" aria-label="Etapas do agendamento">
   <li class="is-step-calendar" data-step="calendar"><span class="stepper-dot" aria-hidden="true"><span class="stepper-num">1</span></span><span class="stepper-label">Data e horário</span></li>
   <li class="is-step-form" data-step="form"><span class="stepper-dot" aria-hidden="true"><span class="stepper-num">2</span></span><span class="stepper-label">Seus dados</span></li>
   <li class="is-step-review" data-step="review"><span class="stepper-dot" aria-hidden="true"><span class="stepper-num">3</span></span><span class="stepper-label">Confirmar</span></li>
  </ol>

  <div class="booking-picker" id="booking-picker">
   <div>
    <h2>Escolha uma data e horário</h2>
    <p class="booking-step-subtitle">Selecione o melhor dia e horário para sua reunião.</p>
    <div class="calendar-toolbar">
     <button type="button" class="calendar-nav" id="month-prev" aria-label="Mês anterior">←</button>
     <strong id="month-label" aria-live="polite"></strong>
     <button type="button" class="calendar-nav" id="month-next" aria-label="Próximo mês">→</button>
    </div>
    <div class="calendar-weekdays" aria-hidden="true"><span>seg</span><span>ter</span><span>qua</span><span>qui</span><span>sex</span><span>sáb</span><span>dom</span></div>
    <div id="booking-calendar" class="public-calendar" aria-label="Calendário de disponibilidade" aria-busy="true"><p>Carregando calendário…</p></div>
    <div class="calendar-status-legend" aria-label="Legenda do calendário">
     <span class="legend-available">Disponível</span>
     <span class="legend-unavailable">Indisponível</span>
    </div>
    <p class="booking-timezone-display"><x-icon name="globe" size="15"/> Horário de Brasília <span class="booking-timezone-code">({{ $timezone }})</span></p>
   </div>
   <aside class="booking-times" id="booking-times" aria-labelledby="slots-title" hidden>
    <h2 id="slots-title">Horários disponíveis</h2>
    <p id="selected-date-label" class="selected-date-label"></p>
    <div id="booking-slots" class="booking-slots" aria-live="polite"></div>
   </aside>
  </div>
  <div class="booking-step-actions" id="calendar-step-actions" hidden>
   <span></span>
   <button type="button" class="button button-primary" id="calendar-continue" disabled>Continuar →</button>
  </div>

  <div class="booking-form-panel" id="booking-form-panel" hidden>
   <h2>Seus dados</h2>
   <p class="booking-step-subtitle">Preencha os dados abaixo para confirmar o agendamento.</p>

   <form method="post" action="{{ route('scheduling.public.store',$eventType) }}" id="booking-form">
    @csrf
    <input type="hidden" name="start" id="selected-start" value="{{ old('start') }}">
    <input type="hidden" name="timezone" id="selected-timezone" value="{{ $timezone }}">
    <input type="hidden" name="form_started_at" value="{{ time() }}">
    <div class="honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>

    <label for="guest_name">Nome completo <span aria-hidden="true">*</span>
     <input id="guest_name" name="guest_name" required maxlength="120" autocomplete="name" value="{{ old('guest_name') }}" @error('guest_name') aria-invalid="true" aria-describedby="guest_name-error" @enderror>
    </label>
    @error('guest_name')<p class="field-error" id="guest_name-error">{{ $message }}</p>@enderror

    <label for="guest_email">E-mail <span aria-hidden="true">*</span>
     <input id="guest_email" type="email" name="guest_email" required maxlength="255" autocomplete="email" value="{{ old('guest_email') }}" @error('guest_email') aria-invalid="true" aria-describedby="guest_email-error" @enderror>
    </label>
    @error('guest_email')<p class="field-error" id="guest_email-error">{{ $message }}</p>@enderror

    <div class="form-pair">
     <label for="guest_phone">Telefone <span>(opcional)</span>
      <input id="guest_phone" type="tel" name="guest_phone" maxlength="40" autocomplete="tel" value="{{ old('guest_phone') }}">
     </label>
     <label for="guest_company">Empresa <span aria-hidden="true">*</span>
      <input id="guest_company" type="text" name="guest_company" required maxlength="120" autocomplete="organization" value="{{ old('guest_company') }}" @error('guest_company') aria-invalid="true" aria-describedby="guest_company-error" @enderror>
     </label>
    </div>
    @error('guest_company')<p class="field-error" id="guest_company-error">{{ $message }}</p>@enderror

    <label for="notes">Assunto da reunião <span>(opcional)</span>
     <textarea id="notes" name="notes" maxlength="2000" placeholder="O que será discutido na reunião?">{{ old('notes') }}</textarea>
    </label>

    @if($errors->hasAny(['start','timezone','guest_phone','guest_company','notes','website','form_started_at']))
     <div class="alert alert-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="booking-step-actions">
     <button type="button" class="button back-button" id="form-back">← Voltar</button>
     <button type="button" class="button button-primary" id="form-continue">Continuar →</button>
    </div>
   </form>
  </div>

  <div class="booking-review-panel" id="booking-review-panel" hidden>
   <h2>Confirmar agendamento</h2>
   <p class="booking-step-subtitle">Confira os dados antes de confirmar.</p>

   <dl class="booking-review-list">
    <div><dt>Reunião</dt><dd>{{ $eventType->description ?: 'Reunião com nossa equipe' }}</dd></div>
    <div><dt>Data</dt><dd id="review-date"></dd></div>
    <div><dt>Horário</dt><dd id="review-time"></dd></div>
    <div><dt>Formato</dt><dd>{{ $eventType->location_value ?: 'Online' }}</dd></div>
    <div><dt>Fuso horário</dt><dd>{{ $timezone }}</dd></div>
    <div><dt>Participante</dt><dd id="review-name"></dd></div>
    <div><dt>E-mail</dt><dd id="review-email"></dd></div>
    <div id="review-phone-row" hidden><dt>Telefone</dt><dd id="review-phone"></dd></div>
    <div id="review-company-row" hidden><dt>Empresa</dt><dd id="review-company"></dd></div>
    <div id="review-notes-row" hidden><dt>Assunto da reunião</dt><dd id="review-notes"></dd></div>
   </dl>

   <div class="booking-step-actions">
    <button type="button" class="button back-button" id="review-back">← Voltar</button>
    <button type="submit" form="booking-form" class="button button-primary booking-confirm" id="review-confirm">Confirmar agendamento</button>
   </div>
  </div>
 </div>
 <footer class="booking-shell-footer"><x-icon name="lock" size="13"/> Ambiente seguro <span>•</span> IRCENTER</footer>
</section>
@endsection
@include('scheduling.public.calendar-script',['calendarUrl'=>route('scheduling.public.calendar',$eventType),'availabilityUrl'=>route('scheduling.public.availability',$eventType),'token'=>null,'formMode'=>true,'durationMinutes'=>$eventType->duration_minutes])
