@extends('layouts.app')
@section('title', 'Cadastro fiscal — '.$client->displayName())
@section('content')
<section class="page-heading"><div><div class="page-eyebrow">Fiscal · Cliente</div><h1>Cadastro fiscal complementar</h1><p>{{ $client->displayName() }} · os dados centrais continuam no cadastro do cliente.</p></div></section>
@if($errors->any())<div class="alert-error">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('clients.fiscal.update', $client) }}" class="panel form-panel">@csrf @method('PUT')
<div class="form-grid">@foreach(['document'=>'CPF/CNPJ','legal_name'=>'Razão social / nome','municipal_registration'=>'Inscrição municipal','state_registration'=>'Inscrição estadual','fiscal_email'=>'E-mail fiscal','phone'=>'Telefone','postal_code'=>'CEP','street'=>'Logradouro','address_number'=>'Número','address_complement'=>'Complemento','district'=>'Bairro','municipality'=>'Município','municipality_code'=>'Código IBGE do município','state'=>'UF','country_code'=>'País (ISO 2)','country_numeric_code'=>'Código numérico do país'] as $field => $label)<label class="form-field"><span>{{ $label }}</span><input name="{{ $field }}" value="{{ old($field, $profile->{$field} ?? ($field === 'country_code' ? 'BR' : '')) }}" @if($field === 'fiscal_email') type="email" @endif></label>@endforeach</div>
<div class="page-actions"><button class="button button-primary" type="submit">Salvar cadastro fiscal</button><a class="button button-secondary" href="{{ route('clients.show', $client) }}">Cancelar</a></div></form>
@endsection
