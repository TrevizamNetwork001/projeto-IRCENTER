# Production gate da API e do webhook Efí

Este runbook prepara uma mudança futura. Ele não autoriza deploy, migration,
criação de credencial, alteração do Nginx ou operação na conta Efí. A execução
exige janela aprovada, responsáveis identificados e evidência externa da
autorização.

Referências obrigatórias:

- [`PRODUCTION.md`](PRODUCTION.md);
- [`DATABASE_BACKUP_RESTORE.md`](DATABASE_BACKUP_RESTORE.md);
- [`API.md`](API.md);
- [`EFI_WEBHOOK_SECURITY.md`](EFI_WEBHOOK_SECURITY.md);
- [`FINANCE_EFI_CHARGES.md`](FINANCE_EFI_CHARGES.md).

## Pré-requisitos de GO

- checkout limpo no commit homologado e todos os commits do ciclo presentes;
- aplicação, PostgreSQL, Redis, filas, workers, scheduler e Nginx saudáveis;
- backup novo do banco Core em formato custom, SHA-256 estável e restore
  completo aprovado em PostgreSQL isolado;
- migrations da API aplicadas com sucesso somente no clone restaurado;
- consumidor, responsável, scopes e procedimento de troca aprovados;
- canal seguro para o token de uso único e operador com acesso ao consumidor;
- operador Efí autorizado, janela e responsável pelo smoke financeiro;
- cópia da configuração Nginx sanitizada validada antes de tocar na ativa;
- artefatos anterior e candidato disponíveis localmente e rollback registrado.

Qualquer ausência acima é NO-GO. Não executar parcialmente a implantação.

## Artefato e migrations

O artefato homologado da aplicação termina em `d8e59f25d603`. O deploy normal
é o build pelo Docker Compose do checkout aprovado, seguido de recreate em
blocos pequenos conforme o runbook de infraestrutura. Migrations não fazem
parte do entrypoint e devem ser executadas explicitamente depois do backup e
do aceite do artefato.

Migrations Core esperadas, nesta ordem:

1. `2026_08_16_150000_create_api_clients_table.php`;
2. `2026_08_16_160000_add_scopes_to_api_clients_table.php`.

Executar `migrate:status` antes e depois. Nunca usar `migrate:fresh`,
`migrate:reset` ou `migrate:refresh` em produção. Depois de criar credenciais
reais, rollback normal não pode remover `api_clients`; preferir correção
forward ou restore formal em desastre.

## Matriz de consumidores

| Consumidor | Responsável | Finalidade | Endpoints | Scopes mínimos | PII | Configuração | Reload | Status |
|---|---|---|---|---|---|---|---|---|
| Documentação Técnica | PENDENTE | Catálogo de clientes e totais operacionais no dashboard | `clients` (lista/detalhe), `users`, `autonomous-systems`, `prefixes` | `documentation.clients.read`, `documentation.users.read`, `documentation.network.read` | Não; o código usa somente ID, código, nomes, estado e datas operacionais | `IRCENTER_API_TOKEN` no `.env` restrito do consumidor; instalação somente por operador autorizado | Recreate gracioso de `documentation-app`, `documentation-queue` e `documentation-scheduler`, um bloco por vez | PENDENTE |

Não conceder `documentation.pii.read` sem nova evidência funcional e aprovação
explícita. Antes da janela, o responsável deve confirmar que esta lista cobre
todos os consumidores conhecidos.

## Entrega da credencial

O comando de criação mostra o token uma única vez. O token não pode passar por
Git, chat, ticket aberto, e-mail comum, documentação ou logs. O mecanismo atual
do consumidor é um arquivo `.env` local com acesso restrito, mas o operador e
o procedimento aprovado de captura e instalação ainda devem ser registrados
fora do checkout.

O plano de troca deve definir antecipadamente:

1. quem executa a criação e quem instala a credencial;
2. como o valor sai do terminal e chega ao arquivo protegido sem persistência
   intermediária insegura;
3. backup protegido da configuração anterior, permissões preservadas e
   descarte seguro da cópia após aceite;
4. recreate gracioso dos serviços do consumidor;
5. smoke 200/403, ausência de PII, headers e `X-Request-ID` sem imprimir token;
6. retorno à configuração anterior em falha, sem revogar antes do aceite.

## Rate limit e legado

Definir explicitamente `DOCUMENTATION_API_CLIENT_RATE_LIMIT=120` na janela e
preservar o limite por IP vigente. Manter
`DOCUMENTATION_API_LEGACY_TOKEN_ENABLED=false`. Não remover fisicamente o hash
legado até concluir o período de observação dos consumidores migrados.

## Sanitização do Nginx

O arquivo ativo é `docker/nginx/conf.d/default.conf`. O formato
`ircenter_main` registra `$uri`, o que revelaria o segredo presente no caminho
do webhook protegido.

Antes de configurar o segredo real, preparar e revisar uma mudança que:

- selecione requisições sob `/api/v1/webhooks/payments/efi/` sem persistir o
  valor usado na seleção;
- exclua essas requisições do `ircenter_main`;
- grave um log dedicado contendo apenas endereço de origem, timestamp, método,
  status, bytes, tempo e request ID;
- não inclua `$request`, `$request_uri`, `$uri`, referer, User-Agent, headers ou
  corpo no formato dedicado;
- preserve o log normal das demais rotas.

Validar primeiro uma cópia completa com `nginx -t`. Na janela, fazer backup do
arquivo ativo, aplicar a mudança revisada, repetir `nginx -t` e usar reload
gracioso. Um callback fictício deve produzir zero ocorrências do valor em todos
os logs relevantes antes da criação do segredo real.

## Atualização da Efí

O mecanismo disponível no código é o método restrito
`EfiPaymentProvider::updateNotificationMetadata`, que envia somente
`notification_url` e `custom_id` para a cobrança existente. Não há comando
Artisan ou painel operacional para essa ação. Antes da janela é obrigatório
identificar o operador autorizado, a forma controlada de invocar o método, o
perfil/credencial Efí, eventual MFA/aprovação e o procedimento de rollback.

Não alterar `client_id`, `client_secret`, certificado, conta ou qualquer outro
dado financeiro. A URL completa protegida é segredo e não integra evidências.

## Ordem obrigatória da janela

1. preflight e snapshot;
2. confirmar backup restaurável e artefatos de rollback;
3. build do commit aprovado sem substituir a imagem anterior;
4. recreate controlado da aplicação;
5. revisar e aplicar somente as migrations esperadas;
6. validar aplicação, banco, Redis, queue, scheduler e financeiro;
7. explicitar rate limits e manter legado desabilitado;
8. criar uma credencial por consumidor e migrar um consumidor por vez;
9. executar smoke seguro da API;
10. aplicar e validar a sanitização Nginx;
11. instalar callback secret por mecanismo protegido e validar fail-closed;
12. comprovar zero ocorrência do segredo em logs;
13. atualizar somente a URL na Efí pelo mecanismo autorizado;
14. executar smoke financeiro controlado, sem simular pagamento local;
15. observar filas, 404/413/429/5xx e saúde por pelo menos 60 minutos.

## Smoke tests

- API autorizada retorna 200, sem PII e com headers de rate limit e request ID;
- endpoint fora dos scopes retorna 403; não gerar burst para testar 429;
- rota antiga do webhook permanece desabilitada;
- segredo incorreto retorna 404 sem receipt, job ou HTTP externo;
- callback controlado cria receipt e job, consulta a Efí e só produz efeito
  financeiro após confirmação remota;
- logs não contêm tokens, Authorization, callback secret ou URL protegida;
- filas não acumulam falhas e nenhum serviço ganha restart inesperado.

## Rollback e critérios de abort

Registrar previamente os IDs das imagens e configurações anteriores. Código é
revertido pelo recreate do bloco afetado com a imagem anterior local.
Configuração Nginx é restaurada do backup somente após `nginx -t`, seguida de
reload. Não reabrir a rota antiga como rollback automático.

Abortar diante de backup não confirmado, árvore suja, artefato divergente,
migration inesperada/falha, serviço unhealthy, erro 5xx crescente, Nginx
inválido, segredo em log, ausência de fail-closed ou efeito financeiro sem
confirmação Efí.

## Aprovações ainda exigidas

- responsável da Documentação Técnica;
- operador autorizado a instalar a credencial no consumidor;
- operador autorizado e mecanismo de execução para a Efí;
- responsável pelo smoke financeiro;
- data, início e duração da janela;
- responsável principal, banco e rollback owner.
