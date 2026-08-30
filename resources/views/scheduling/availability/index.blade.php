@extends('layouts.app')
@section('title','Disponibilidade — Agenda')
@section('content')
<div class="page-header"><div><h1>Disponibilidade</h1><p>{{ $eventType->name }}</p></div></div>
@include('scheduling.admin.nav')
@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
<div class="detail-grid">
 <section class="panel"><h2>Disponibilidade semanal</h2>
 @forelse($rules as $rule)<div class="availability-row"><span>{{ [1=>'SEG',2=>'TER',3=>'QUA',4=>'QUI',5=>'SEX',6=>'SÁB',7=>'DOM'][$rule->day_of_week] }}</span><strong>{{ substr($rule->start_time,0,5) }} — {{ substr($rule->end_time,0,5) }}</strong><small>{{ $rule->timezone }}</small>@if(auth()->user()->canOperate())<form method="post" action="{{ route('scheduling.admin.rules.delete',[$eventType,$rule]) }}">@csrf @method('DELETE')<button type="submit" class="link-danger">Remover</button></form>@endif</div>@empty<p class="empty-state">Nenhuma disponibilidade padrão.</p>@endforelse
 @if(auth()->user()->canOperate())<form method="post" action="{{ route('scheduling.admin.rules.store',$eventType) }}" class="form-grid">@csrf<label>Dia<select name="day_of_week">@foreach([1=>'SEG',2=>'TER',3=>'QUA',4=>'QUI',5=>'SEX',6=>'SÁB',7=>'DOM'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label><label>Início<input type="time" name="start_time" required></label><label>Fim<input type="time" name="end_time" required></label><label>Timezone<input name="timezone" value="{{ config('scheduling.default_timezone') }}" required></label><button class="button">Adicionar janela</button></form>@endif
 </section>
 <section class="panel"><h2>Exceções</h2>
 @forelse($exceptions as $exception)<div class="availability-row"><span>{{ $exception->date->format('d/m/Y') }}</span><strong>{{ $exception->type==='unavailable'?'Bloqueado':'Disponível' }}</strong><small>{{ $exception->start_time ? substr($exception->start_time,0,5).'–'.substr($exception->end_time,0,5) : 'Dia inteiro' }}{{ $exception->reason ? ' · '.$exception->reason : '' }}</small>@if(auth()->user()->canOperate())<form method="post" action="{{ route('scheduling.admin.exceptions.delete',[$eventType,$exception]) }}" class="confirm-delete" data-confirm="Remover esta exceção?">@csrf @method('DELETE')<button type="submit" class="link-danger">Remover</button></form>@endif</div>@empty<p class="empty-state">Nenhuma exceção cadastrada.</p>@endforelse
 @if(auth()->user()->canOperate())<form method="post" action="{{ route('scheduling.admin.exceptions.store',$eventType) }}" class="form-grid">@csrf<label>Data<input type="date" name="date" required></label><label>Tipo<select name="type"><option value="unavailable">Bloquear</option><option value="available_override">Liberar extraordinariamente</option></select></label><label>Início (opcional)<input type="time" name="start_time"></label><label>Fim (opcional)<input type="time" name="end_time"></label><label>Motivo<input name="reason" maxlength="255"></label><button class="button">Cadastrar exceção</button></form>@endif
 </section>
</div>
@endsection
@push('scripts')<script nonce="{{ request()->attributes->get('csp_nonce') }}">document.querySelectorAll('.confirm-delete').forEach(form=>form.addEventListener('submit',event=>{if(!confirm(form.dataset.confirm))event.preventDefault();}));</script>@endpush
