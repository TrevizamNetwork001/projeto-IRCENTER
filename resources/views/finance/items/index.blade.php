@extends('layouts.app')
@section('title', 'Itens de cobrança — IRCENTER')
@section('content')
@include('finance._nav')
<section class="page-heading"><div><div class="page-eyebrow"><span class="status-dot"></span>Financeiro</div><h1>Itens de cobrança</h1><p>Catálogo reutilizável de serviços e produtos cobrados.</p></div></section>
@if(session('success'))<div class="alert-success">{{ session('success') }}</div>@endif
<section class="panel">
@if(auth()->user()->isAdministrator())
<details class="finance-create-box"><summary class="button button-primary">+ Novo item de cobrança</summary>
<form method="POST" action="{{ route('finance.items.store') }}" class="form-grid finance-inline-form">@csrf
<div class="field-group"><label>Nome <span>*</span></label><input class="form-control" name="name" required maxlength="255"></div>
<div class="field-group"><label>Valor padrão <span>*</span></label><input class="form-control" name="default_amount" inputmode="decimal" required placeholder="150,00"></div>
<div class="field-group field-span-2"><label>Descrição</label><textarea class="form-control" name="description" rows="2"></textarea></div>
<label class="field-group"><span>Disponibilidade</span><span><input type="checkbox" name="active" value="1" checked> Ativo</span></label>
<div class="form-actions"><button class="button button-primary">Salvar item</button></div></form></details>
@endif
<form class="filter-bar" method="GET"><div class="filter-search"><x-icon name="search" size="18"/><input name="search" value="{{ $search }}" placeholder="Nome ou descrição"></div><button class="button button-secondary">Buscar</button></form>
@if($items->isEmpty())<div class="empty-state empty-state-large"><div><strong>Nenhum item de cobrança</strong><span>Cadastre o primeiro item reutilizável.</span></div></div>
@else<div class="table-responsive"><table class="data-table"><thead><tr><th>Nome</th><th>Descrição</th><th>Valor</th><th>Status</th><th>Ações</th></tr></thead><tbody>
@foreach($items as $item)<tr><td><strong>{{ $item->name }}</strong></td><td>{{ Str::limit($item->description ?: '—', 90) }}</td><td>R$ {{ number_format((float)$item->default_amount,2,',','.') }}</td><td><span class="status-pill {{ $item->active ? 'is-active':'is-inactive' }}">{{ $item->active ? 'Ativo':'Inativo' }}</span></td><td>
@if(auth()->user()->isAdministrator())<details><summary class="table-action-link">Editar</summary><form method="POST" action="{{ route('finance.items.update',$item) }}" class="finance-compact-form">@csrf @method('PUT')<input class="form-control" name="name" value="{{ $item->name }}" required><input class="form-control" name="default_amount" value="{{ $item->default_amount }}" required><textarea class="form-control" name="description">{{ $item->description }}</textarea><label><input type="checkbox" name="active" value="1" @checked($item->active)> Ativo</label><button class="button button-secondary">Salvar</button></form></details><form method="POST" action="{{ route('finance.items.toggle',$item) }}">@csrf @method('PATCH')<button class="table-action-link">{{ $item->active?'Inativar':'Ativar' }}</button></form>@endif
</td></tr>@endforeach</tbody></table></div>{{ $items->links() }}@endif
</section>
@endsection
