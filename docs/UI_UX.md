# Padrão visual — UI/UX

## Contexto

A tela de **Usuários** (`/users`) foi usada como piloto de um padrão visual
mais denso e hierarquizado para o IRCENTER. A referência de linguagem visual
usada nesta fase foi o ConsultaDesk (outro produto da Trevizam Network),
**apenas** nos seguintes aspectos:

- densidade (menos espaço vertical desperdiçado);
- hierarquia clara entre informação primária e secundária;
- uso de badges/pills em vez de texto solto para estado e perfil;
- tabela mais informativa (identidade com avatar, ações compactas);
- filtros compactos em vez de campos soltos em card grande.

O ConsultaDesk **não** foi copiado como identidade visual. O IRCENTER mantém
sua própria paleta escura (tokens `--cyan`/`--violet`/`--amber`/`--green`/
`--danger` em `public/assets/app.css`), tipografia (Inter), sidebar e
estrutura de layout. Esta fase não alterou nenhum desses elementos.

Nenhuma mudança de backend, rota, permissão ou regra de negócio foi feita.
Apenas apresentação (Blade + CSS de `public/assets/app.css`, que é o
stylesheet real servido pelo layout — o pipeline Vite/Tailwind em
`resources/css/app.css` não é usado pelas views principais do app).

## Componentes reaproveitados (não criados nesta fase)

Já existiam e foram simplesmente **aplicados corretamente** pela primeira vez
na tela de Usuários (que usava classes inexistentes no CSS, como
`.status-badge` e `.table-secondary`, herdadas de uma versão antiga do
stylesheet):

- `<x-user-avatar :user="$user" size="small"/>` — avatar de iniciais ou
  símbolo escolhido pelo usuário. Puramente visual, sem upload de foto.
- `.status-pill.is-active` / `.is-inactive` — usado também em Clientes.
- `.filter-search` / `.filter-select` — usado também em Clientes/Incidentes.
- `.table-secondary-text`, `.table-mono`, `.table-actions-column` — usado em
  várias tabelas do sistema.
- `.pagination-simple` — paginação compacta usada em Clientes.
- `.empty-state` / `.empty-state-icon` / `.empty-state-large`.
- `<x-icon name="..."/>` — sistema de ícones SVG inline existente.

## Componentes novos (pequenos, genéricos, reutilizáveis)

Criados porque não existia equivalente, seguindo a mesma convenção das
classes acima (tokens CSS existentes, sem cor hardcoded fora de tema):

- **`.role-pill`** (`is-admin` / `is-operator` / `is-viewer`) — badge de
  perfil, análogo ao `.status-pill` e ao `.irr-type-pill` já existentes.
  Usa os valores reais de `User::roles()` — não inventa perfis novos.
- **`.table-identity` / `.table-identity-text`** — célula de tabela com
  avatar + nome (peso maior) + informação secundária (peso menor), para
  qualquer tabela que precise apresentar identidade de usuário/entidade.
- **`.table-icon-button`** — botão de ação circular, ícone apenas, com
  `aria-label` e `title` obrigatórios no uso, foco visível herdado da regra
  global `:focus-visible`, tamanho de toque de 30×30px.
- Ícones `edit` (lápis) e `users` adicionados a `resources/views/components/
  icon.blade.php`, seguindo o padrão de `stroke`/`viewBox` já usado nos
  demais ícones do componente.

## Padrão de cabeçalho de página

```blade
<section class="page-heading">
    <div>
        <div class="page-eyebrow">
            <span class="status-dot"></span>
            {{-- categoria/contexto --}}
        </div>
        <h1>{{-- título --}}</h1>
        <p>{{-- subtítulo curto --}}</p>
    </div>
    <a class="button button-primary" href="...">{{-- ação principal --}}</a>
</section>
```

Este é o mesmo padrão já usado em Clientes — não é novo, só passou a ser
seguido também em Usuários (que antes usava uma variação com `<div>` em vez
de `<section>` e sem o indicador visual no eyebrow).

## Padrão de filtros

Uma `<form class="filter-bar">` com:

- um `.filter-search` (ícone de busca + input) para texto livre;
- um ou mais `.filter-select` para facetas (perfil, estado, etc.);
- um `button-secondary` para aplicar;
- um `button-ghost` "Limpar" que só aparece quando algum filtro está ativo,
  linkando para a rota sem query string.

`.filter-bar` já colapsa em coluna abaixo de 760px (regra existente em
`public/assets/app.css`), sem necessidade de CSS adicional para mobile.

## Padrão de tabela

Ordem de colunas por prioridade: identidade → categorização (badges) →
dado temporal/contextual → ações. Ações mutáveis compactas (ícone + aria-
label + title), nunca texto grande tipo `[ Editar ]`. Empty state sempre
com ícone + título + explicação curta, nunca uma tabela vazia "quebrada".

## Dark / light

Todas as classes usadas (novas e reaproveitadas) dependem exclusivamente de
custom properties já redefinidas em `html[data-theme="light"]`
(`--cyan`, `--violet`, `--green`, `--danger`, `--border`, `--surface-raised`,
`--text-muted`, etc.). Nenhum valor de cor foi hardcoded fora desses tokens.

## Acessibilidade

- Ação de ícone único sempre com `aria-label` (não só `title`).
- Foco visível herdado da regra global `:focus-visible` do projeto.
- Estado (`Ativo`/`Bloqueado`) diferenciado por **texto e cor**, nunca só
  cor.
- Tabela semântica (`<thead>`/`<th>`), sem mudança na leitura por teclado.

## Guia para expansão futura

Ao aplicar este padrão a outra tela (Clientes já o segue; ASNs, Prefixos,
Financeiro, Fiscal, Agenda e Configurações **não foram tocados** nesta
fase):

1. Reaproveitar as classes desta lista antes de criar qualquer CSS novo.
2. Se precisar de um badge novo, seguir a convenção `<contexto>-pill` com
   modificador `is-<estado>`, cores via token existente.
2. Célula de identidade: sempre `.table-identity` + `.table-identity-text`,
   nunca duplicar a estrutura inline.
3. Não introduzir dependência de JS ou framework novo só por causa de UI.
4. Validar responsividade em 1440×900, 1366×768, 768×1024 e 390×844, e os
   dois temas, antes de considerar a tela pronta.
