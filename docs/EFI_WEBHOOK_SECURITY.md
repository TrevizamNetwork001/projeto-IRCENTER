# Endurecimento do webhook Efí

O callback protegido usa uma URL completa no formato:

`https://HOST/api/v1/webhooks/payments/efi/{segredo-hexadecimal-de-64-caracteres}`

Gere o segredo uma única vez com uma fonte criptograficamente segura (por
exemplo, `bin2hex(random_bytes(32))`) e configure o mesmo valor em
`EFI_WEBHOOK_CALLBACK_SECRET` e na URL de notificação cadastrada na Efí. O
segredo não deve ser enviado a logs, tickets ou repositórios.

## Implantação sem interrupção

O ambiente atualmente possui uma URL legada ativa. Antes do deploy, configure
temporariamente `EFI_WEBHOOK_LEGACY_ROUTE_ENABLED=true`. Em seguida, atualize a
URL na Efí, confirme entregas pela rota protegida e altere a opção para `false`.
A rota legada é bloqueada por padrão e nunca aceita chamadas quando o segredo
configurado está ausente ou inválido.

## Requisito do proxy reverso

O Nginx atualmente versionado fora deste repositório registra `$uri`; portanto,
ele registraria o segredo presente no caminho. Antes de ativar a nova URL, a
rota do webhook deve usar um access log dedicado cujo formato não inclua
`$request`, `$request_uri` nem `$uri`. Preserve apenas dados operacionais como
horário, IP, status, duração e o header de resposta `X-Request-ID`. Não desative
os logs globalmente.

O limite padrão é 30 requisições por minuto e por IP. Ele pode ser ajustado por
`EFI_WEBHOOK_RATE_LIMIT` entre 1 e 300 após observar o volume legítimo.

## Processamento concorrente

O processamento assíncrono de uma mesma notificação é serializado por receipt
com o middleware nativo `WithoutOverlapping`. Entregas repetidas do mesmo token
reutilizam o mesmo receipt por meio da restrição única `(provider, token_hash)`;
por isso, o ID do receipt é a unidade lógica do lock.

A chave do lock contém somente um namespace da aplicação, um discriminador do
job e o ID interno do receipt. Ela nunca contém o token da notificação, o
segredo do callback, credenciais Efí ou o conteúdo criptografado do token.

Detalhes arquiteturais, parâmetros de retry, requisitos do Redis e instruções
para executar a prova multiprocesso estão em
[EFI_WEBHOOK_CONCURRENCY.md](EFI_WEBHOOK_CONCURRENCY.md).
