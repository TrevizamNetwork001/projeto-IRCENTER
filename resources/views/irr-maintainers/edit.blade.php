@extends('layouts.app')

@section('title', 'Editar maintainer IRR — IRCENTER')

@section('content')
    <section class="page-heading">
        <div>
            <div class="page-eyebrow">Internet Routing Registry — TC</div>
            <h1>Editar maintainer</h1>
            <p>{{ $maintainer->mntner }} — {{ $maintainer->formattedAsn() }}</p>
        </div>
    </section>

    <section class="panel form-panel">
        <form method="POST" action="{{ route('irr-maintainers.update', $maintainer) }}">
            @method('PUT')

            @include('irr-maintainers._form', [
                'submitLabel' => 'Salvar alterações',
                'cancelUrl' => route('irr-maintainers.show', $maintainer),
            ])
        </form>
    </section>
@endsection
