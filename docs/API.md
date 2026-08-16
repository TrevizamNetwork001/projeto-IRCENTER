# IRCENTER API v1

A API pública de documentação usa JSON e o base path `/api/v1`. Em produção, todas as chamadas devem usar HTTPS. O contrato normativo está em [`openapi/ircenter-api-v1.yaml`](openapi/ircenter-api-v1.yaml).

## Autenticação e credenciais

Envie a credencial individual no cabeçalho `Authorization: Bearer <credencial>`. O token é opaco, não é JWT. Novas integrações devem obrigatoriamente obter uma credencial individual com `php artisan api-client:create`; o valor gerado deve ser guardado pelo consumidor, pois o servidor armazena somente seu hash.

O mecanismo global legado está depreciado, desabilitado por padrão e existe apenas para uma transição operacional controlada. Ele não deve ser usado por novas integrações. Operadores podem consultar seu estado com `php artisan api-client:legacy-status` e o uso auditado com `php artisan api-client:legacy-usage`, sem exposição de credenciais.

Scopes disponíveis:

| Scope | Acesso |
|---|---|
| `documentation.clients.read` | Clientes |
| `documentation.users.read` | Usuários |
| `documentation.network.read` | Sistemas autônomos e prefixos |
| `documentation.pii.read` | Adicional; libera PII somente em endpoints já autorizados |

Sem `documentation.pii.read`, campos pessoais são omitidos — não são retornados como `null` nem mascarados. Esse scope nunca autoriza um endpoint sozinho.

## Paginação e filtros

As listagens aceitam `page` e `per_page`; o padrão de `per_page` é 25 e valores acima de 100 são limitados a 100. Valores de tipo inválido ou menores que 1 retornam 422. O corpo usa a paginação nativa atual do Laravel, incluindo `current_page`, `data`, URLs, `links`, `per_page` e totais.

`active_only` é aceito em todas as listagens. Sistemas autônomos e prefixos também aceitam `client_id`; prefixos aceitam `ip_version` com valor 4 ou 6. A especificação OpenAPI contém regras e exemplos completos.

## Rate limit e rastreabilidade

O limite vigente é aplicado por IP antes da autenticação. Respostas 429 preservam `X-RateLimit-Limit`, `Retry-After` e demais cabeçalhos produzidos pelo framework.

Toda resposta possui `X-Request-ID`, um UUID gerado pelo servidor. Se o cliente enviar esse cabeçalho, o valor não é reutilizado. Em erros, o mesmo valor aparece em `error.request_id`.

## Erros

Erros usam `error.code`, `error.message` e `error.request_id`; validações também usam `error.details`. Durante a transição, `message` permanece no topo, depreciado, para compatibilidade. Os códigos públicos são `authentication_required`, `invalid_token`, `insufficient_scope`, `resource_not_found`, `validation_error`, `rate_limit_exceeded` e `internal_error`.

## Versionamento

Mudanças compatíveis podem ocorrer dentro de `/api/v1`. Remover ou renomear campo ou endpoint, alterar tipo ou semântica, ou tornar obrigatório algo antes opcional exige nova versão ou estratégia explícita de transição.

## Webhook Efí

O webhook não faz parte deste contrato. Antes de ativar sua URL secreta em produção, continua obrigatório sanitizar o log do Nginx externo para que o segmento secreto não seja registrado em `$uri`; consulte [`EFI_WEBHOOK_SECURITY.md`](EFI_WEBHOOK_SECURITY.md).
