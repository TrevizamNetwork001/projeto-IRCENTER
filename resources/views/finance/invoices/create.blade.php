@extends('layouts.app')
@section('title', 'Nova cobrança avulsa — IRCENTER')
@section('content')
@include('finance._nav')
<section class="page-heading"><div><div class="page-eyebrow"><span class="status-dot"></span>Financeiro</div><h1>Gerar cobrança avulsa</h1><p>Crie primeiro a fatura. A emissão Efí será confirmada separadamente no detalhe.</p></div></section>
@if($errors->any())<div class="alert-error">{{ $errors->first() }}</div>@endif
<section class="panel"><form method="POST" action="{{ route('finance.invoices.store') }}">@csrf
<div class="finance-steps"><span>1 Cliente</span><span>2 Item e valor</span><span>3 Vencimento</span><span>4 Revisão</span></div>
<div class="form-grid"><div class="field-group field-span-2"><label for="client_id">Cliente <span>*</span></label><select class="form-control" id="client_id" name="client_id" required><option value="">Selecione...</option>@foreach($clients as $client)<option value="{{ $client->id }}" @selected((int)old('client_id',$selectedClientId)===$client->id)>{{ $client->displayName() }} — {{ $client->document }}</option>@endforeach</select></div>
<div class="field-group field-span-2"><label for="billing_item_id">Item de cobrança <span>*</span></label><select class="form-control" id="billing_item_id" name="billing_item_id" required><option value="">Selecione...</option>@foreach($items as $item)<option value="{{ $item->id }}" data-amount="{{ $item->default_amount }}" @selected((int)old('billing_item_id')===$item->id)>{{ $item->name }} — R$ {{ number_format((float)$item->default_amount,2,',','.') }}</option>@endforeach</select></div>
<div class="field-group"><label>Quantidade <span>*</span></label><input class="form-control" name="quantity" value="{{ old('quantity','1') }}" required></div><div class="field-group"><label>Valor unitário <span>*</span></label><input class="form-control" id="unit_amount" name="unit_amount" value="{{ old('unit_amount') }}" required></div><div class="field-group"><label>Vencimento <span>*</span></label><input class="form-control" type="date" name="due_on" value="{{ old('due_on',now()->addDays(10)->toDateString()) }}" required></div></div>
<div class="finance-review-note"><strong>Revisão</strong><span>Nenhuma comunicação com a Efí ocorre nesta etapa.</span></div><div class="form-actions"><a class="button button-secondary" href="{{ route('finance.invoices.index') }}">Cancelar</a><button class="button button-primary">Criar fatura</button></div></form></section>
<script nonce="{{ request()->attributes->get('csp_nonce') }}">document.getElementById('billing_item_id').addEventListener('change',function(){const o=this.options[this.selectedIndex];if(o.dataset.amount)document.getElementById('unit_amount').value=o.dataset.amount;});</script>
@endsection
