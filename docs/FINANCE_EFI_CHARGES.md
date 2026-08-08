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
`paid` e `overdue`. `canceled` e `failed` não bloqueiam outra decisão de
cobrança, embora a chave única continue impedindo reutilizar a mesma reserva.
O `lockForUpdate` da Invoice serializa a decisão em bancos com lock de linha;
SQLite em memória cobre a invariante sequencial, não concorrência física real.

## Dados locais Efí

O preflight aceita apenas `boleto` e `boleto_pix`, moeda BRL, valor Decimal
positivo convertido em centavos inteiros e vencimento ISO. Valida nome/razão
social, CPF/CNPJ, e-mail, telefone brasileiro, CEP, logradouro, número, bairro,
cidade e UF. O telefone remove DDI 55 somente para entradas de 12/13 dígitos
que começam por 55. O snapshot da Invoice e o cadastro Core não são alterados.

O payload mantém `metadata.custom_id` igual à chave de idempotência estável.
O resultado persiste o ID Efí, status normalizado, link HTTPS comprovado e Pix
copia-e-cola (`pix.qrcode`) quando presentes. Respostas parciais são aceitas.
Não há fixture comprovando PDF ou linha digitável separados nesta versão; por
isso não existem colunas especulativas nem persistência do JSON remoto.

Status Efí: `new`, `waiting`, `identified`, `approved` e `unpaid` viram `open`;
`paid`/`settled`, `paid`; `expired`, `overdue`; `canceled`, `canceled`;
`refunded`/`contested` e desconhecidos, `failed`. Status desconhecido jamais é
tratado como pago.

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
