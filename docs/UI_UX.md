# Padrão visual — UI/UX

## Contexto

As telas de **Usuários** (`/users` — listagem, edição e criação) foram
usadas como piloto de um padrão visual mais denso e hierarquizado para o
IRCENTER. A referência de linguagem visual usada nesta fase foi o
ConsultaDesk (outro produto da Trevizam Network), **apenas** nos seguintes
aspectos:

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

## Padrão de formulário com painel de apoio

Nas telas de editar/criar usuário, o formulário principal foi envolvido em
`.split-layout` (novo, pequeno) — grid de duas colunas (conteúdo principal +
painel estreito), com a mesma proporção já usada pelo `.dashboard-grid` do
Dashboard, mas com nome genérico para não acoplar semanticamente a uma tela
específica. Colapsa para uma coluna abaixo de 1080px (mesma regra do
`.dashboard-grid`).

```blade
<div class="split-layout">
    <div class="split-layout-main">
        {{-- painéis principais, empilhados --}}
    </div>

    <aside class="tip-card">
        <span class="tip-card-icon"><x-icon name="shield" size="17"/></span>
        <div>
            <h3>{{-- título curto --}}</h3>
            <p>{{-- texto de apoio, só leitura --}}</p>
        </div>
    </aside>
</div>
```

`.tip-card` é puramente informativo — não introduz nenhum campo, permissão
ou dado novo. Foi inspirado no cartão de dica do ConsultaDesk, mas usa a
paleta e os tokens do próprio IRCENTER (`--cyan`/`--cyan-soft`), não a
identidade visual do ConsultaDesk.

**Importante:** o ConsultaDesk usa um sistema de permissões granulares por
feature (toggles individuais). O IRCENTER **não tem esse conceito** — só os
três papéis fixos de `User::roles()`. Essa parte do ConsultaDesk foi
propositalmente **não replicada**, para não inventar um sistema de
permissões que não existe no backend.

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

## Meu Perfil (fase PROFILE-UX-1)

`/profile` continua sendo página própria acessível pelo menu (nada virou
modal). O que mudou foi o conteúdo: de 3 cards independentes (identidade,
dados da conta, grade de avatares ocupando a maior parte da tela) para um
**container único** (`.profile-container`) com seções internas separadas
por `.profile-section` (Informações da conta, Aparência, Segurança).

### Identidade e seletor de imagem

O cabeçalho de identidade (`.profile-identity`) mostra avatar/foto + nome +
e-mail + `.role-pill` (role é só informativo, sem edição). A biblioteca de
avatares temáticos existente (`User::avatars()`) **foi preservada**, mas
não fica mais fixa na página — vive dentro de um `<dialog>` nativo aberto
por um botão de câmera discreto (`.profile-camera-button`, `aria-label`
"Alterar imagem do perfil"). `<dialog>` nativo dá de graça foco preso,
fechar com Escape e backdrop, sem lib nova.

Três modos de identidade, um só campo de verdade (`avatar_mode`:
`initials` | `avatar` | `photo`) mais os campos que já existiam
(`avatar_key`) e um novo (`avatar_photo_path`):

- **iniciais**: `avatar_mode=initials`, deriva de `User::initials()`
  (já existia, sem mudança — máx. 2 letras, funciona com nome único).
- **avatar temático**: `avatar_mode=avatar` + `avatar_key` (fluxo existente
  de `profile.avatar.update`, só ganhou o campo de modo).
- **foto**: `avatar_mode=photo` + `avatar_photo_path` (novo).

Fallback em `User::hasPhotoAvatar()`/`hasThemedAvatar()`: foto > avatar
temático > iniciais. Remover a foto restaura automaticamente o avatar
temático anterior se houver, senão iniciais — implementado em
`ProfileController::removePhoto()`.

`x-user-avatar` (`resources/views/components/user-avatar.blade.php`) é o
componente único que decide o que renderizar; é reaproveitado no topo do
sistema (`layouts/app.blade.php`) e na tela de Usuários. Escolher uma foto
no Perfil reflete automaticamente no avatar do topo, sem lógica duplicada.

### Upload de foto — segurança

- Formatos aceitos: **JPEG, PNG, WebP**. SVG nunca é aceito (a regra
  `image` do Laravel já exclui SVG por padrão).
- Limite: **2 MB** (`UploadProfilePhotoRequest::MAX_KILOBYTES`).
- Validação real, não apenas por extensão/Content-Type do navegador: a
  combinação `image` + `mimes:jpeg,jpg,png,webp` do Laravel usa
  `getimagesize()` (decodificação real) e MIME detectado via `finfo` no
  conteúdo do arquivo, não no que o cliente declarou.
- Storage: disco `public` já existente (`storage/app/public`), nome de
  arquivo gerado pelo Laravel (hash aleatório), nunca o nome original —
  sem path traversal, sem escolha de caminho pelo usuário.
- Substituir uma foto apaga o arquivo anterior do disco
  (`ProfileController::uploadPhoto`); remover foto também apaga o arquivo.
- Sem Gravatar, sem API externa, sem envio de dados pra fora do IRCENTER.
- IDOR: nenhuma rota de foto/avatar aceita `user_id` do cliente — a
  identidade vem sempre de `$request->user()`/`auth()->user()`.
- CSRF: herdado do middleware global do projeto, como todo o resto.
- **Não implementado nesta fase** (ambiente sem GD/Imagick instalado no
  container `app`): redimensionamento/normalização para 512×512 e remoção
  de metadados EXIF. A imagem é guardada como enviada, só validada e
  limitada a 2 MB. Documentado aqui para decisão futura — instalar GD ou
  Imagick na imagem PHP é a mudança de infraestrutura necessária para
  fechar esse ponto, fora do escopo desta fase (não alterar Docker).

### Aparência

O IRCENTER só tem alternância binária Claro/Escuro (`localStorage`
`ircenter-theme`, sem "Sistema"/auto). A seção Aparência do Perfil usa um
`.segmented-control` com 2 opções que lê/escreve exatamente essa mesma
chave — não foi criado um segundo mecanismo de tema.

### Segurança

"Alterar minha senha" saiu do canto superior da página (onde competia
visualmente com o cabeçalho) e entrou na seção Segurança, ao lado da data
da última alteração. Continua linkando para `profile.password.edit`, rota
e fluxo de troca de senha **inalterados** (senha atual + nova + confirmação
+ CSRF + auditoria, tudo como já era).

### Ativação pendente em produção

Duas coisas precisam rodar em produção antes da foto de perfil funcionar
de verdade lá, nenhuma delas executada nesta fase:

1. Migration `2026_08_30_170000_add_photo_avatar_to_users_table` (adiciona
   `avatar_mode` e `avatar_photo_path` a `users`, com backfill de
   `avatar_mode='avatar'` para quem já tinha `avatar_key` escolhido).
2. `php artisan storage:link` no container `app`, se ainda não tiver sido
   rodado — sem o symlink `public/storage`, a URL da foto retorna 404
   mesmo com o arquivo salvo corretamente no disco.
