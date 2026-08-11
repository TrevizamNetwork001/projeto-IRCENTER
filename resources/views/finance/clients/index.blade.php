@extends('layouts.app')
@section('title', 'Clientes financeiros — IRCENTER')
@section('content')
@include('finance._nav')
<section class="page-heading">
    <div><div class="page-eyebrow"><span class="status-dot"></span>Financeiro</div><h1>Clientes</h1><p>Recorrências e cobranças organizadas por cliente.</p></div>
    @if($financeEnabled && auth()->user()->isAdministrator())<a class="button button-primary" href="{{ route('finance.invoices.create') }}">Nova cobrança avulsa</a>@endif
</section>
<section class="panel">
<form class="filter-bar" method="GET"><div class="filter-search"><x-icon name="search" size="18"/><input name="search" value="{{ $search }}" placeholder="Cliente, código ou documento"></div><button class="button button-secondary">Buscar</button>@if($search!=='')<a class="button button-ghost" href="{{ route('finance.clients.index') }}">Limpar</a>@endif</form>
@if($rows->isEmpty())<div class="empty-state empty-state-large"><div><strong>Nenhum cliente encontrado</strong><span>Ajuste a busca ou cadastre um cliente ativo.</span></div></div>@else
<div class="table-responsive"><table class="data-table"><thead><tr><th>Cliente</th><th>Cobrança recorrente</th><th>Valor</th><th>Geração</th><th>Vencimento</th><th>Status</th><th>Ações</th></tr></thead><tbody>
@foreach($rows as $row)@php($primary=$row['primary'])<tr>
<td><a class="table-primary-link" href="{{ route('finance.clients.show',$row['client']) }}">{{ $row['client']->displayName() }}</a><span class="table-secondary-text">{{ $row['client']->client_code }}</span></td>
<td>@if($primary){{ $primary->items->first()?->description ?: 'Sem item configurado' }}@if($row['contracts_count']>1)<span class="table-secondary-text">{{ $row['active_count'] }} ativa(s) + {{ $row['contracts_count']-$row['active_count'] }} histórica(s)</span>@endif @else<span class="table-secondary-text">Sem cobrança recorrente</span>@endif</td>
<td>{{ $primary ? 'R$ '.number_format($row['total'],2,',','.') : '—' }}</td><td>{{ $primary ? 'Dia '.$primary->generation_day : '—' }}</td><td>{{ $primary ? 'Dia '.$primary->due_day : '—' }}</td>
<td>@if($primary)<span class="status-pill {{ $primary->status==='active'?'is-active':'is-inactive' }}">{{ $primary->status==='active'?'Ativo':'Requer configuração' }}</span>@else<span class="status-pill is-inactive">Não configurado</span>@endif</td>
<td><a class="table-action-link" href="{{ route('finance.clients.show',$row['client']) }}">{{ $primary?'Abrir':'Configurar cobrança' }}</a></td>
</tr>@endforeach</tbody></table></div>{{ $clients->links() }}@endif
</section>
@endsection
