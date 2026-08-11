# Cobranças Efí

## Fluxo e fronteira de submissão

`CreateChargeForInvoice` monta o `PaymentChargeRequest` a partir do snapshot
imutável da Invoice. Providers que implementam `PreflightsPaymentCharges`
validam e montam localmente o payload antes de qualquer escrita, OAuth ou HTTP.
Erro nessa etapa é corrigível e não cria Charge nem evento de reserva.

Depois do preflight, uma transação bloqueia a Invoice, procura uma Charge em
estado bloqueante e, somente se não existir, reserva uma Charge `submitting`.
O POST ocorre fora da transação. Resposta conhecida persiste o ID e os
artefatos; exceção depois de iniciada a chamada externa produz
`submission_unknown`. Esse estado significa que a criação remota pode ter
ocorrido e nunca autoriza retry automático.

Estados bloqueantes: `submitting`, `submission_unknown`, `created`, `open`,
`paid`, `overdue` e `failed`. `failed` é conservadoramente bloqueante porque
pode representar uma cobrança externa posteriormente devolvida, contestada ou
um status remoto ainda desconhecido. Somente `canceled` não bloqueia: ele é
usado apenas após cancelamento remoto confirmado, nunca para timeout, erro
local ou resultado incerto. Uma nova emissão após cancelamento continua sendo
uma decisão explícita do operador, não um retry automático.
O `lockForUpdate` da Invoice serializa a decisão em bancos com lock de linha;
SQLite em memória cobre a invariante sequencial, não concorrência física real.

## Dados locais Efí

O preflight aceita apenas `boleto` e `boleto_pix`, moeda BRL, valor Decimal
positivo convertido em centavos inteiros e vencimento ISO. Valida nome/razão
social, CPF/CNPJ, e-mail, telefone brasileiro, CEP, logradouro, número, bairro,
cidade e UF. O telefone remove DDI 55 somente para entradas de 12/13 dígitos
que começam por 55. O snapshot da Invoice e o cadastro Core não são alterados.

O payload deriva `metadata.custom_id` da chave de idempotência estável,
substituindo somente `:` por `_` e rejeitando qualquer outro caractere não
suportado antes de OAuth/HTTP. O resultado persiste o ID Efí, status
normalizado, links HTTPS de checkout/boleto/PDF, barcode textual e Pix
copia-e-cola quando presentes. O SVG/base64 de `pix.qrcode_image` não é
persistido. Fixtures sanitizadas cobrem os formatos reais de create e detail.

Status Efí: `new`, `waiting`, `identified`, `approved` e `unpaid` viram `open`;
`paid`/`settled`, `paid`; `expired`, `overdue`; `canceled`, `canceled`;
`refunded`/`contested` e desconhecidos, `failed`. Status desconhecido jamais é
tratado como pago.

## Matriz de risco dos estados locais

| Status local | Risco externo | Bloqueia nova emissão? | Reconciliável hoje? | Observação |
|---|---|---:|---:|---|
| `pending` | não iniciado | não | não | Não é criado pelo fluxo Efí atual. |
| `submitting` | submissão em curso | sim | sim | Nunca reenviar. |
| `submission_unknown` | criação pode ter ocorrido | sim | sim | Exige GET/correlação. |
| `created` | cobrança criada | sim | não | Resultado externo conhecido. |
| `open` | obrigação aberta | sim | não | Cobrança operacional. |
| `paid` | obrigação liquidada | sim | não | Histórico financeiro definitivo. |
| `overdue` | obrigação vencida | sim | não | Continua exigível. |
| `failed` | cobrança criada, refund/contestação ou status desconhecido | sim | não pela UI atual | Exige revisão; não implica segurança para reemitir. |
| `canceled` | cancelamento remoto confirmado | não | não | Nova emissão deliberada é permitida. |

`refunded`, `contested` e qualquer status remoto Efí ainda desconhecido são
mapeados para `failed`. Assim permanecem visíveis como falha/revisão, nunca
viram `paid` e não liberam nova submissão. Uma sincronização futura poderá
atualizá-los por um fluxo GET explícito; esta fase não amplia o botão de
reconciliação além de `submitting`/`submission_unknown`.

## Erros, reconciliação e segurança

Timeout, conexão interrompida, 5xx, resposta inválida e qualquer falha após o
início da tentativa permanecem conservadoramente `submission_unknown`. A
integração ainda não possui uma taxonomia Efí comprovada de 4xx inequívocos;
portanto 4xx após o POST também permanece incerto. Mensagens brutas não chegam
à Web e logs/auditoria guardam somente contexto sanitizado e classe do erro.

A reconciliação executa OAuth e GETs: usa ID remoto quando disponível ou busca
por `custom_id` exato em janela da criação. Zero resultados mantém incerteza,
um atualiza idempotentemente e múltiplos bloqueiam como ambiguidade. Nunca faz
POST, cancelamento ou retry. Produção/live, webhook e automação seguem
bloqueados pelas flags e pela trava adicional da Web.

## Runbook do próximo smoke controlado (não executar nesta fase)

1. Obter autorização explícita para uma única emissão real em homologação.
2. Confirmar flags: Finance ligado; automação, live e webhooks desligados;
   provider `efi`; ambiente `homologation`.
3. Registrar contagens read-only de invoices, charges, auditoria, receipts e
   provider events; confirmar também a Charge histórica id=2 sem alteração.
4. Criar por fluxo autorizado uma Invoice nova, limpa, de valor controlado,
   com snapshot completo e método `boleto_pix`.
5. Abrir a tela, conferir `EFÍ — HOMOLOGAÇÃO`, marcar a confirmação e digitar
   `EMITIR EFI HOMOLOGACAO` uma única vez.
6. Submeter uma vez; conferir exatamente um POST nos logs sanitizados, uma nova
   Charge, `provider_charge_id`, status, link HTTPS e Pix copia-e-cola.
7. Conferir o evento `charge.submission_reserved` e `charge.created`, sem
   segredos, e comparar as contagens antes/depois.
8. Repetir o clique/POST Web apenas para provar o bloqueio local: a mesma Charge
   deve ser exibida e nenhum novo POST pode ocorrer.
9. Se o resultado ficar incerto, não repetir; executar somente reconciliação
   read-only e preservar evidências.

Incidente histórico: a Charge id=2 foi criada pela arquitetura anterior, que
reservava antes da validação do telefone. Ela deve permanecer
`submission_unknown`, sem ID remoto, sem alteração manual e sem retry.

## Webhook e confirmação de pagamento

O endpoint futuro é `POST /api/v1/webhooks/payments/efi`. Ele não usa sessão
Web nem CSRF, aceita somente form/JSON/multipart, limita o body a 4096 bytes e
fica oculto com 404 enquanto `PAYMENT_WEBHOOKS_ENABLED=false`. O callback Efí
contém apenas o identificador `notification`; nunca é suficiente para mudar o
domínio financeiro.

O token é validado sintaticamente, criptografado com `APP_KEY` e identificado
por SHA-256. A combinação provider + hash é única: replays retornam sucesso
idempotente, geram auditoria de duplicidade e não enfileiram novo trabalho. O
token puro, credenciais, Authorization e payload HTTP bruto não são gravados.

O Job usa OAuth e `GET /v1/notification/{token}`. Somente o histórico obtido
por esse GET autenticado produz `payment_provider_events`. Eventos são únicos
globalmente por provider + ID remoto, e `payload_json` permanece nulo: são
guardados apenas campos normalizados necessários. Falhas HTTP/timeout deixam o
receipt em `failed`, sem alterar Charge, Payment ou Invoice, permitindo retry
controlado pela fila.

`SyncChargeFromProvider` centraliza transições e baixa. Eventos antigos ou
`waiting` posterior a `paid` não regridem o estado. `expired` vira `overdue`
sem cancelar a Invoice; `canceled` não cria nova cobrança; `refunded`,
`contested` e desconhecidos ficam `failed`/revisão. Um status pago confirmado
cria no máximo um Payment por Charge. Como a Efí não fornece ID de liquidação
separado nesse histórico, o `provider_event_id` pago é a referência remota do
Payment. A constraint adicional provider + referência protege replay.

O Payment registra valor, moeda e data bancária. Se o valor for exatamente o
total da Invoice, ela muda uma única vez para `paid`. Valor ausente, menor ou
maior gera `payment.amount_mismatch`; o recebimento confirmado é preservado,
mas a Invoice não é baixada automaticamente. Pagamento parcial não é inferido
nesta fase.

Eventos auditáveis incluem recebimento/duplicidade/processamento do webhook,
sincronização ou descarte de transição, confirmação da Charge, criação do
Payment, divergência e baixa da Invoice. A tela da Invoice exibe pagamentos e
uma timeline compacta sem payloads ou segredos.

### Runbook do webhook real futuro

1. Aplicar a migration financeira pendente após backup e janela autorizada.
2. Confirmar endpoint HTTPS público, limites e TLS.
3. Manter produção/live e automação bloqueadas.
4. Ativar `PAYMENT_WEBHOOKS_ENABLED` somente em janela explícita.
5. Registrar a URL na Efí apenas com autorização separada.
6. Enviar callback controlado e confirmar receipt único, GET autenticado,
   evento normalizado e nenhuma criação de cobrança.
7. Repetir o mesmo callback para provar idempotência.
8. Desativar a flag se qualquer confirmação GET falhar de forma inesperada.
