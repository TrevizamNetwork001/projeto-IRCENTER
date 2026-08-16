@extends('layouts.app')
@section('title', 'Emitente fiscal — IRCENTER')
@section('content')
<section class="page-heading"><div><div class="page-eyebrow"><a class="inline-link" href="{{ route('fiscal.dashboard') }}">Fiscal</a> · Configurações</div><h1>Emitente fiscal</h1><p>Configure os dados oficiais da empresa que emitirá documentos fiscais.</p></div></section>
@if(session('success'))<div class="alert-success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert-error" role="alert">{{ $errors->first() }}</div>@endif
<section class="panel"><header class="panel-header"><div><span class="panel-eyebrow">Status</span><h2>{{ $profile?->active && $readiness['status'] === 'READY' ? 'Ativo' : ($profile ? 'Inativo' : 'Incompleto') }}</h2></div><span class="status-pill {{ $readiness['status'] === 'READY' ? 'is-success' : 'is-pending' }}">{{ $readiness['status'] }}</span></header>
@if($readiness['status'] !== 'READY')<p>Preencha os dados fiscais reais da empresa. O emitente só poderá ser usado após o cadastro válido.</p>@endif</section>
<form method="POST" action="{{ route('fiscal.issuer.update') }}" class="panel form-panel">@csrf @method('PUT')
<div class="form-grid">
@foreach(['legal_name'=>'Razão social','trade_name'=>'Nome fantasia','document'=>'CNPJ','municipal_registration'=>'Inscrição municipal','municipality_code'=>'Código IBGE do município','municipality'=>'Município','state'=>'UF','postal_code'=>'CEP','street'=>'Logradouro','address_number'=>'Número','address_complement'=>'Complemento','district'=>'Bairro','phone'=>'Telefone','email'=>'E-mail'] as $field=>$label)
<label class="form-field"><span>{{ $label }}</span><input name="{{ $field }}" value="{{ old($field, $profile?->{$field}) }}" @if($field === 'email') type="email" @endif @if(in_array($field,['legal_name','document','municipality_code','municipality','state','postal_code','street','address_number','district'],true)) required @endif></label>
@endforeach
<label class="form-field"><span>Status operacional</span><select name="active"><option value="0" @selected((string)old('active', $profile?->active ? '1' : '0') === '0')>Inativo</option><option value="1" @selected((string)old('active', $profile?->active ? '1' : '0') === '1')>Ativo</option></select><small>Ative somente após conferir todos os dados oficiais.</small></label>
</div><div class="page-actions"><button class="button button-primary" type="submit">Salvar emitente</button><a class="button button-secondary" href="{{ route('fiscal.dashboard') }}">Cancelar</a></div></form>
@endsection
