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

Certificados, senhas, tokens e chaves privadas não são tratados nesta fase. A FISCAL-2 deverá definir um mecanismo seguro e externo à tabela de emitente antes de integrar a NFS-e Nacional em homologação.

## Preparação para FISCAL-2

A próxima fase implementará um adapter oficial isolado, schemas vigentes, DPS, autenticação, assinatura, consulta, cancelamento e download oficial. Produção continuará fora do escopo até homologação específica.
