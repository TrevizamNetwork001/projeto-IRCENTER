@extends('layouts.app')
@section('title', 'Fiscal — IRCENTER')
@section('content')
<section class="page-heading">
    <div><div class="page-eyebrow"><span class="status-dot"></span>Fiscal</div><h1>NFS-e e documentos fiscais</h1><p>Fundação interna para preparação e acompanhamento fiscal.</p></div>
    <span class="status-pill is-pending">{{ $environment === 'homologation' ? 'Homologação' : 'Produção' }}</span>
</section>
<section class="finance-overview-cards">
    <article><span>Emitente</span><strong>{{ $issuerCount }}</strong></article><article><span>Clientes com cadastro fiscal</span><strong>{{ $customerCount }}</strong></article><article><span>Serviços classificados</span><strong>{{ $serviceCount }}</strong></article><article><span>Documentos fiscais</span><strong>{{ $documentCount }}</strong></article><article class="{{ $pendingCount ? 'requires-attention' : '' }}"><span>Pendências</span><strong>{{ $pendingCount }}</strong></article>
</section>
<section class="panel"><header class="panel-header"><div><span class="panel-eyebrow">Documentos</span><h2>Documentos fiscais</h2></div></header>
@if($documents->isEmpty())<div class="empty-state"><div><strong>Nenhum documento fiscal emitido</strong><span>Esta fase mantém apenas a fundação interna; não há emissão real.</span></div></div>
@else<div class="table-responsive"><table class="data-table"><thead><tr><th>Documento</th><th>Competência</th><th>Valor</th><th>Status</th></tr></thead><tbody>@foreach($documents as $document)<tr><td class="table-mono">{{ $document->public_id }}</td><td>{{ $document->competence_date->format('d/m/Y') }}</td><td>R$ {{ number_format((float) $document->net_amount, 2, ',', '.') }}</td><td>{{ $document->status->value }}</td></tr>@endforeach</tbody></table></div>@endif
</section>
@endsection
