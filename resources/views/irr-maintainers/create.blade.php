@extends('layouts.app')

@section('title', 'Novo maintainer IRR — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">Internet Routing Registry — TC</div>
            <h1>Novo maintainer</h1>
            <p>Registre a senha de um mntner já existente no TC para publicar objetos em seu nome.</p>
        </div>
    </section>

    <section class="panel">
        <p>
            <strong>Criar um mntner novo não é possível por esta tela.</strong>
            O TC exige senha de override para criar o objeto <code>mntner</code>, disponível
            apenas no cadastro inicial do AS. Se o AS ainda não tem um mntner no TC, faça o
            cadastro pelo
            <a href="https://bgp.net.br/wizard.html" target="_blank" rel="noopener">wizard oficial do TC</a>
            antes de continuar aqui.
        </p>
    </section>

    <section class="panel form-panel">
        <form method="POST" action="{{ route('irr-maintainers.store') }}">
            @include('irr-maintainers._form', [
                'submitLabel' => 'Cadastrar maintainer',
                'cancelUrl' => route('irr-maintainers.index'),
            ])
        </form>
    </section>
@endsection
