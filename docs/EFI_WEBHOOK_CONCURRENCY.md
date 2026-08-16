# Concorrência no webhook Efí

Este documento descreve a proteção contra processamento simultâneo da mesma
notificação financeira Efí, introduzida na fase FIN-WEBHOOK-2.

## Objetivo e limites

O lock impede que jobs referentes à mesma notificação executem ao mesmo tempo.
Isso reduz OAuth e consultas repetidas, disputas de atualização e efeitos
financeiros concorrentes.

O lock não confirma pagamentos, não substitui a idempotência do banco e não
descarta notificações posteriores. A confirmação continua seguindo o fluxo:

1. o webhook protegido recebe a notificação;
2. o controller cria ou reutiliza o receipt;
3. o job é despachado;
4. o job consulta a Efí por OAuth e chamada autenticada;
5. somente a resposta do provider orienta a sincronização financeira.

## Unidade lógica do lock

O controller calcula SHA-256 do token recebido e executa `insertOrIgnore` na
tabela `payment_webhook_receipts`. A restrição única sobre `(provider,
token_hash)` garante que entregas repetidas do mesmo token Efí reutilizem o
mesmo registro, mesmo quando chegam concorrentemente.

Após a inserção, o controller consulta o registro pela mesma dupla e incrementa
`receive_count` sob `lockForUpdate`. Cada entrega despacha um job, mas todos os
jobs daquela notificação recebem o mesmo `receipt_id`.

Por esse motivo, a chave escolhida é o ID do receipt. Não foi necessário criar
fingerprint adicional, variável de ambiente ou migration.

### Formato seguro

O `ProcessEfiPaymentWebhook` utiliza `WithoutOverlapping` com:

- prefixo: `ircenter:finance:efi:webhook:receipt:`;
- discriminador interno da classe do job, acrescentado pelo Laravel;
- ID numérico do receipt.

O formato lógico é:

```text
ircenter:finance:efi:webhook:receipt:{job}:{receipt_id}
```

O valor real do discriminador `{job}` é uma implementação interna do Laravel e
não deve ser reproduzido manualmente. A chave não inclui token original,
`token_hash`, token criptografado, callback secret, `client_secret`,
`Authorization` ou qualquer credencial.

## Política do `WithoutOverlapping`

Parâmetros atuais:

| Parâmetro | Valor | Motivo |
| --- | ---: | --- |
| `releaseAfter` | 10 segundos | Reagenda o job sobreposto em vez de descartá-lo. |
| `expireAfter` | 60 segundos | Remove lock órfão com margem sobre o timeout do job. |
| `timeout` | 30 segundos | Limite máximo já adotado para uma execução. |
| `retryUntil` | 10 minutos | Dá espaço para contenção sem permitir retry infinito. |
| `maxExceptions` | 5 | Limita falhas reais do provider separadamente da contenção. |
| `tries` | 0 | Evita que releases por lock esgotem um contador pequeno de tentativas. |
| `backoff` | 10, 30, 120 e 300 segundos | Preserva a política existente para exceções reais. |

Quando o lock está livre, o job entra na região protegida e processa o receipt.
Quando está ocupado, o middleware devolve o job à fila após 10 segundos. O job
adiado não altera o receipt, não chama OAuth/Efí, não cria pagamento e não gera
`payment_webhook.token_not_found`. Depois da liberação, ele pode executar
normalmente e descobrir eventos novos que tenham surgido para o mesmo token.

Não é usado `dontRelease()`, porque isso descartaria uma entrega que pode ser
legítima. Também não é usado `ShouldBeUnique`, pois o requisito é impedir
simultaneidade, não deduplicar permanentemente notificações.

## Store e requisitos operacionais

O middleware obtém o lock pelo cache padrão do Laravel. Em produção,
`CACHE_STORE` deve continuar apontando para o Redis compartilhado por todos os
workers. Stores locais por processo, como `array`, não oferecem exclusão mútua
entre workers e não são apropriadas para produção.

Antes de implantar ou alterar workers, confirme:

```bash
php artisan about --only=cache
```

Não execute `cache:clear`, `FLUSHDB` ou `FLUSHALL` no Redis produtivo para testar
esta funcionalidade. Uma indisponibilidade do cache não elimina as constraints
e verificações de banco, que permanecem como defesa definitiva de consistência.

## Idempotência preservada

A exclusão mútua complementa, mas não substitui:

- unicidade do receipt por `(provider, token_hash)`;
- constraints dos eventos do provider;
- unicidade de pagamentos e referências do provider;
- transações e verificações de estado existentes;
- `lockForUpdate` usado na atualização do receipt;
- capacidade de reprocessar o mesmo token para descobrir eventos novos.

As chamadas HTTP à Efí não foram movidas para dentro de uma transaction de
banco. O controller continua apenas persistindo o recebimento e despachando o
job; a lógica de confirmação financeira não foi alterada.

## Testes automatizados

Os testes de feature em `tests/Feature/EfiPaymentWebhookTest.php` verificam:

- presença e parâmetros do `WithoutOverlapping`;
- determinismo da chave para o mesmo receipt;
- diferença entre chaves de receipts distintos;
- ausência de tokens e segredos na chave;
- reagendamento por 10 segundos quando o lock está ocupado;
- ausência de chamada HTTP e efeito financeiro durante overlap;
- manutenção do status do receipt durante a contenção;
- execução permitida após liberação do lock.

### Prova multiprocesso com Redis isolado

Execute a partir da raiz do projeto:

```bash
sh tests/Concurrency/run-efi-webhook-lock.sh
```

O runner usa o `compose.e2e.test.yaml` existente, um projeto Compose separado e
Redis efêmero. Ele impõe `APP_ENV=testing`, exige host Redis `e2e-redis`, bloqueia
HTTP externo real e cria dois processos PHP independentes:

1. o processo A adquire o lock e permanece dois segundos na chamada HTTP fake;
2. o processo B tenta a mesma chave durante esse intervalo e é adiado;
3. após A liberar o lock, B é executado novamente e entra;
4. contadores atômicos no Redis comprovam duas execuções totais e no máximo uma
   execução simultânea.

O script fotografa IDs, estados e contadores de restart dos containers de
produção antes e depois. Qualquer mudança faz o teste falhar. O ambiente efêmero
é removido ao final, inclusive em caso de erro.

Esse teste não usa Redis, fila, PostgreSQL, certificados ou credenciais de
produção. PostgreSQL não é necessário para a prova do mutex: nenhuma migration
foi criada e as constraints de banco existentes continuam cobertas pela suíte
funcional.

## Validação da fase

Na implementação original da FIN-WEBHOOK-2 foram obtidos os seguintes
resultados:

- testes do webhook: 29 testes e 168 asserções;
- testes do provider Efí: 39 testes e 87 asserções;
- regressão da API de documentação: 25 testes e 182 asserções;
- suíte segura completa: 410 testes e 1.542 asserções;
- prova multiprocesso com Redis 8.8 efêmero: aprovada;
- `php -l`, Pint e `git diff --check`: aprovados;
- containers e caches produtivos: inalterados.

O commit de implementação é `0aa01c5` (`fix: evita processamento concorrente
do webhook efi`).

## Diagnóstico

Em caso de fila acumulada, verifique primeiro disponibilidade e latência do
Redis compartilhado, duração das chamadas à Efí, quantidade de workers e falhas
reais do provider. Contenção não deve ser classificada como falha financeira.

Nunca registre a chave completa se políticas internas considerarem IDs de
receipts sensíveis. Para correlação operacional, prefira `receipt_id` e
`request_id`, sem token, headers ou segredos.

Não altere `releaseAfter`, `expireAfter`, `retryUntil`, `maxExceptions` ou
`timeout` isoladamente: esses valores formam uma política conjunta e qualquer
mudança deve incluir teste de contenção e nova prova multiprocesso.
