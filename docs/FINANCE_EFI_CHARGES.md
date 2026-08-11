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
POST, cancelamento ou retry. Produção/live e automação seguem bloqueados pelas
flags e pela trava adicional da Web. O webhook pode ser habilitado
separadamente em homologação sem liberar criação live.

## Emissão homologada

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

As Charges históricas 3 e 4 permanecem `submission_unknown`, sem ID remoto e
sem retry. A Charge 5 (`provider_charge_id=45002412`) comprovou emissão,
artefatos e recuperação após perda de resposta, sem segundo POST.

## Webhook e confirmação de pagamento

O endpoint é `POST /api/v1/webhooks/payments/efi`. Ele não usa sessão
Web nem CSRF, aceita somente form/JSON/multipart, limita o body a 4096 bytes e
fica oculto com 404 enquanto `PAYMENT_WEBHOOKS_ENABLED=false`. O callback Efí
contém apenas o identificador `notification`; nunca é suficiente para mudar o
domínio financeiro.

O token é validado sintaticamente, criptografado com `APP_KEY` e identificado
por SHA-256. A combinação provider + hash é única porque o receipt representa
o ciclo agregado daquele token, não uma entrega isolada. `received_at` registra
a primeira entrega; `last_received_at` e `receive_count` registram recorrência.
O token puro, credenciais, Authorization e payload HTTP bruto não são gravados.

Na Efí, o mesmo token pode ser entregue quando a transação está `waiting` e
novamente quando passa a `paid`. Por isso, todo POST válido atualiza o receipt e
agenda uma nova consulta, mesmo quando o token já é conhecido. Token repetido
não significa evento duplicado. A resposta 2xx pode indicar
`duplicate_token=true`, mas sempre confirma `processing_scheduled=true`.

O Job usa OAuth e `GET /v1/notification/{token}`. Somente o histórico obtido
por esse GET autenticado produz `payment_provider_events`. Eventos são únicos
globalmente por provider + ID remoto, e `payload_json` permanece nulo: são
guardados apenas campos normalizados necessários. Todo o histórico retornado é
ordenado deterministicamente por data de criação e ID remoto; eventos já vistos
são ignorados e eventos novos são aplicados. Consultar o mesmo histórico dez
vezes é seguro. Falhas HTTP/timeout deixam o receipt em `failed`, sem marcar
eventos, e um callback posterior com o mesmo token agenda nova tentativa.

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

Exceção explícita: a baixa manual Efí `settled` não informa `value` no
histórico. Como essa operação confirma integralmente a própria cobrança, o
Payment usa seu valor nominal persistido. A exceção não se aplica a eventos
bancários `paid` sem valor, que continuam bloqueados. O reprocessamento de um
evento `settled` já visto pode concluir um Payment ausente e permanece
idempotente pelas constraints de Charge e referência remota.

Eventos auditáveis incluem recebimento/processamento do webhook,
sincronização ou descarte de transição, confirmação da Charge, criação do
Payment, divergência e baixa da Invoice. A tela da Invoice exibe pagamentos e
uma timeline compacta sem payloads ou segredos.

As constraints e os locks protegem callbacks concorrentes no PostgreSQL. Os
testes SQLite cobrem interleaving e idempotência lógica, mas não reproduzem
contenção física simultânea do PostgreSQL; essa limitação deve ser considerada
no smoke controlado.

As identidades são distintas: o notification token identifica o ciclo remoto;
cada POST é uma entrega; cada entrada do histórico é um provider event; e o
Payment é o efeito financeiro único. O fluxo é `POST token → atualizar receipt
→ GET /notification/token → eventos ainda não vistos → SyncChargeFromProvider
→ Payment/Invoice`.

### Runbook do webhook em homologação

1. Confirmar que a migration financeira de Payment/webhook foi aplicada após
   backup verificável.
2. Confirmar endpoint HTTPS público, limites e TLS.
3. Manter produção/live e automação bloqueadas.
4. Ativar `PAYMENT_WEBHOOKS_ENABLED` somente em janela explícita.
5. Atualizar somente `notification_url` e `custom_id` da Charge existente pelo
   método restrito `updateNotificationMetadata`, após autorização separada.
6. Enviar callback controlado e confirmar receipt agregado, GET autenticado,
   evento normalizado e nenhuma criação de cobrança.
7. Repetir o mesmo token, confirmar novo GET e provar idempotência pelos IDs dos
   eventos e pela unicidade do Payment.
8. Desativar a flag se qualquer confirmação GET falhar de forma inesperada.
