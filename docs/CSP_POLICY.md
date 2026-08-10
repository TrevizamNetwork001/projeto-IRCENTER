# Content Security Policy do IRCENTER

## Estado da H5

A aplicação envia `Content-Security-Policy-Report-Only`. A política ainda não
bloqueia recursos: esta etapa serve para observar o comportamento real,
corrigir violações e preparar o enforcement sem interromper login, aplicação
ou integrações. Não há endpoint `report-uri`/`report-to` nesta fase, evitando
criar um coletor público suscetível a abuso.

Política atual (o valor do nonce muda a cada resposta):

```text
default-src 'self'; base-uri 'self'; object-src 'none';
frame-ancestors 'none'; frame-src 'none'; form-action 'self';
img-src 'self' data:; font-src 'self';
connect-src 'self' https://viacep.com.br;
script-src 'self' 'nonce-<por-resposta>'; script-src-attr 'none';
style-src 'self' 'nonce-<por-resposta>';
style-src-attr 'none'; media-src 'self'; worker-src 'self';
manifest-src 'self';
```

Não são permitidos `unsafe-eval`, curinga global, esquemas `http:`/`https:`
genéricos, frames, objetos ou embeds. `https://viacep.com.br` é a única origem
externa: o formulário de clientes consulta CEP por `fetch`. Imagens SVG são
inline ou locais; `data:` fica limitado a imagens. CSS, JavaScript, fontes
(incluindo o fallback local de Instrument Sans) e demais assets são same-origin.
Não foram encontrados CDN, Bunny Fonts carregada da rede, iframe, mídia,
worker, `blob:` ou chamadas XHR adicionais.

### Inventário da `documentation-app`

A inspeção somente leitura encontrou CSS e JavaScript locais, um `fetch` para
rota same-origin no editor visual de topologia e SVG inline. O layout de
relatório contém um bloco `<style>` e um `onclick="window.print()"`; a página
`welcome` contém o CSS inline padrão do Laravel/Vite. Links externos para o app
principal e páginas do ecossistema Laravel são navegação, não carregamento de
subrecursos. Não foram encontrados iframe, object/embed, fontes/CDN externas,
`data:`, `blob:`, worker ou mídia. Essa aplicação não possui middleware próprio
de security headers no estado inspecionado.

## Nonce e conteúdo inline

O middleware gera 18 bytes com `random_bytes` e os codifica em Base64 para cada
resposta. O valor existe apenas no atributo da requisição, no header e nos
elementos `<script>`/`<style>` renderizados; não é persistido nem registrado.
Os scripts de tema, senha, menus, clientes, IRR e workflows usam esse nonce.
O handler `onclick` da página de erro foi convertido para listener com nonce.
O CSS inline de fallback da página `welcome` também usa nonce.

O gradiente calculado no servidor para o gráfico do dashboard é declarado em
um bloco `<style>` protegido pelo nonce da resposta. Não restam atributos
`style` nas views da aplicação e `style-src-attr` permanece definido como
`'none'`.

## Observação e validação manual

Sem coletor no servidor, as violações devem ser observadas no console do
navegador e na aba Network em homologação controlada. Não enviar conteúdo dos
relatórios para logs da aplicação. Checklist manual:

- login: tema claro/escuro, localStorage, exibir/ocultar senha, SVG de fundo e CSS;
- dashboard: menus, notificações, gráfico e modais;
- clientes: formulários, máscaras e consulta de CEP;
- financeiro e fiscal: listagens, detalhes, formulários e ações;
- IRR/RPKI: formulários, cópia e workflows;
- diagnóstico, erros 403/404/419/429/500/503 e troca de senha;
- páginas e relatórios da aplicação de documentação.

## Migração para enforcement

Fluxo: **Report-Only → observação → correção das violações → testes E2E →
enforcement**.

Antes de trocar o nome do header, é obrigatório observar uma janela definida
em homologação/produção, eliminar violações legítimas, executar a futura suíte
E2E, validar todos os itens do checklist e
revisar novamente as origens. A `documentation-app` deve receber patch e commit
próprios depois que seu trabalho pendente estiver separado; ela não foi
alterada na H5 atual.

Rollback: remover somente o header Report-Only e a propagação do nonce, ou
reverter o commit da H5. Como a política não está em enforcement, o rollback
não exige liberar origens e não deve envolver deploy fora do processo
controlado.
