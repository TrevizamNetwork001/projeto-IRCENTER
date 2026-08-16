# Fiscal / NFS-e — FISCAL-1

## Escopo

A FISCAL-1 cria a fundação interna de domínio, persistência, segurança e interface do módulo Fiscal. Ela não emite NFS-e, não acessa governo ou prefeitura, não transmite XML, não usa certificado e não reage a pagamentos.

```text
Customer
   ↓
Fiscal Profile
   ↓
Fiscal Document
   ↓
Snapshot
   ↓
Fiscal Provider
   ↓
[Fake na FISCAL-1]
   ↓
Transmission / Events / Artifacts
```

## Arquitetura

As tabelas usam a conexão existente `finance_fiscal`. `FiscalCustomerProfile` estende o cliente do Core por `core_client_id`, sem duplicá-lo e sem foreign key entre bancos. `FiscalServiceProfile` pode apontar opcionalmente para o catálogo `billing_items`. `FiscalDocument` existe independentemente de cobrança e seus vínculos com contrato, fatura, cobrança e item são opcionais.

O documento possui itens, idempotências, tentativas e artefatos. Eventos como `document.created`, `document.ready` e `artifact.stored` reutilizam `domain_audit_events`, com sanitização centralizada.

## Estados e snapshots

Estados internos: `draft → ready → processing → authorized | rejected`; rejeitados podem voltar a `ready`, e autorizados podem ir a `cancelled`. Transições fora desse grafo são bloqueadas.

Ao preparar um rascunho, são congelados `issuer_snapshot`, `customer_snapshot`, `service_snapshot`, `values_snapshot` e `tax_snapshot`. Depois de `prepared_at`, o model impede alterações nesses campos. Mudanças futuras nos cadastros não alteram o histórico.

## Providers e ambiente

O contrato `NfseProvider` expõe `prepare`, `validate`, `issue`, `query`, `cancel` e `downloadArtifacts`. A FISCAL-1 oferece somente `FakeNfseProvider` (determinístico e sem rede) e `DisabledNfseProvider` (bloqueia toda operação).

Defaults seguros:

```dotenv
FISCAL_ENABLED=false
FISCAL_PROVIDER=fake
FISCAL_ENVIRONMENT=homologation
FISCAL_ARTIFACT_DISK=local
```

Com a flag desligada, rotas retornam 404, navegação e seção fiscal do cliente não aparecem, e actions operacionais falham fechadas. Não existem jobs ou scheduler fiscais.

## Segurança e artefatos

A escrita do perfil fiscal exige administrador, Form Request, CSRF e route model binding. Documentos usam atributos permitidos explicitamente. Mensagens persistidas devem ser sanitizadas e credenciais nunca pertencem às entidades fiscais.

Artefatos guardam somente tipo, disco, caminho, MIME, SHA-256 e tamanho. `FiscalArtifactStore` rejeita disco público; o padrão `local` aponta para `storage/app/private`. Conteúdo não é gravado no PostgreSQL nem registrado em logs.

Certificados, senhas, tokens e chaves privadas não são tratados nesta fase. Uma futura integração automática deverá definir um mecanismo seguro e externo à tabela de emitente.

## Configuração do emitente

O cadastro do emitente é uma operação administrativa normal em **Fiscal → Configuração do emitente**. Na ausência de dados oficiais, o dashboard indica `Emitente pendente` e a tela de novo documento permanece disponível com orientação para configurar o emitente; nenhum rascunho pode ser criado até existir um cadastro ativo e válido.

Somente administradores podem criar ou editar o cadastro. A ativação exige razão social, CNPJ válido, município, código IBGE, UF e endereço completos. A atualização é transacional, mantém no máximo um emitente ativo e registra criação, alteração, ativação ou desativação em `domain_audit_events`. Dados reais devem ser preenchidos pelo operador; não pertencem a migrations, seeders ou ao repositório.

## FISCAL-2A — Operação manual assistida

A FISCAL-2A acrescenta criação explícita de rascunhos, avaliação centralizada de prontidão (`READY`, `WARNING` e `BLOCKED`), retorno para revisão, resumo baseado no snapshot e registro assistido de uma NFS-e que o usuário já autorizou no Portal Nacional. O modo de emissão é registrado separadamente como `manual`; o provider continua sendo outro conceito.

O fluxo operacional é:

```text
Rascunho → readiness → snapshot/ready → emissão manual no Portal Nacional
         → confirmação forte no IRCENTER → authorized → XML/PDF privados
```

O registro manual exige confirmação expressa, número e data de autorização. Guarda usuário responsável, chave/código opcionais e evento sanitizado em `domain_audit_events`. Um documento autorizado não aceita alteração silenciosa de competência, tomador, serviço, valores, metadados oficiais ou snapshots. `ready` pode voltar a `draft`; `draft` pode ser cancelado apenas internamente. Não existe cancelamento oficial nesta fase.

XML bem formado e PDF com MIME/assinatura coerentes podem ser anexados a `FiscalArtifact`. Os arquivos ficam no disco privado, com nome sanitizado, tamanho e SHA-256; download e remoção passam pela aplicação e autorização. O XML não é interpretado como autoridade e não é validado contra XSD nesta fase.

O link para o Portal Nacional é somente uma navegação iniciada pelo usuário. A aplicação não consulta o portal, não automatiza login ou preenchimento e não realiza qualquer HTTP fiscal. A listagem, os filtros, cards, pendências, timeline e a seção fiscal do cliente operam apenas sobre dados locais.

> Registrar uma NFS-e como emitida no IRCENTER não emite nem valida a nota perante o governo.

## Limites e preparação para FISCAL-2B

A FISCAL-2A não implementa DPS/XML oficial, certificado, assinatura, mTLS, API SEFIN, scraping, cancelamento oficial, emissão por pagamento, jobs ou produção. A FISCAL-2B poderá substituir somente o passo manual por um provider automático, preservando documento, snapshots, idempotência, transmissões, auditoria e artefatos.

## FISCAL-2A.1 — Homologação prática do fluxo manual

O fluxo local foi homologado com fixtures sintéticas desde a criação do rascunho até prontidão, congelamento dos snapshots, retorno para revisão, novo preparo, registro manual, anexação e download privado de XML/PDF, timeline, cliente e filtros. A criação prioriza cliente, serviço, competência, descrição e valor; o emitente ativo e o vínculo do serviço com o item financeiro são preenchidos internamente, e vínculos financeiros continuam opcionais.

A prontidão usa mensagens operacionais e associa cada pendência a uma área. CPF/CNPJ, nome/razão social, município, UF e código IBGE do tomador, emitente ativo completo, serviço ativo/classificado, descrição e valores positivos bloqueiam o preparo. E-mail fiscal e complemento de endereço são avisos opcionais. Ao preparar, a interface avisa que os snapshots serão congelados e que nenhuma informação será enviada ao governo.

Em `ready`, o resumo para o Portal Nacional é organizado por tomador, serviço, competência, valores e dados complementares, sem IDs, paths ou JSON. A cópia integral e as cópias pontuais usam Clipboard API com fallback local. O link abre apenas a URL pública do Portal Nacional em nova aba, sem parâmetros ou automação.

O registro manual deixa explícito que a NFS-e deve ter sido emitida antes no Portal. Número e data/hora são obrigatórios; chave, código de verificação e observação são opcionais; a confirmação expressa permanece obrigatória. Depois de autorizado, documento, metadados oficiais, snapshots e itens históricos são imutáveis. A origem exibida é `Registro manual`, separada do provider técnico.

XML deve ser bem formado e é lido com `LIBXML_NONET`; PDF exige MIME e assinatura `%PDF-`. Ambos respeitam o limite configurado, recebem nome sanitizado, SHA-256 e armazenamento privado. Downloads validam usuário, vínculo do artifact e existência do arquivo; o caminho de storage nunca é apresentado. Ausência de XML/PDF após emissão é informativa, não uma falha.

O cancelamento disponível é somente o descarte interno de rascunho e não representa cancelamento fiscal oficial. A aplicação não gera DPS, não assina XML, não transmite NFS-e, não usa certificado, não chama rede fiscal e mantém `FISCAL_ENABLED=false` como default seguro. Uma fase futura poderá integrar um provider nacional, sem ser requisito para operar manualmente.
