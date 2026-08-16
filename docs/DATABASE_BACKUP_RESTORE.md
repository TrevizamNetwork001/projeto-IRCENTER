# Backup e verificação de restore — Financeiro/Fiscal

## Objetivo e escopo

Este procedimento é o gate de backup anterior a migrations na conexão Laravel `finance_fiscal`. Em produção, ela usa PostgreSQL no serviço Compose `postgres`, banco separado `ircenter_finance`, no mesmo cluster do banco Core. Senhas e DSNs nunca devem ser impressos ou passados como argumentos.

O backup lógico de `ircenter_finance` é suficiente para migrations exclusivamente dessa conexão. O banco Core também deve ser incluído se uma futura janela contiver migrations Core ou se o escopo aprovado assim exigir. Redis e cópias do volume PostgreSQL não substituem o dump lógico.

Referências obrigatórias: `/opt/ircenter/docs/BACKUP_POLICY.md` e `/opt/ircenter/docs/HARDENING_DEPLOY_RUNBOOK.md`.

## Ferramentas e destino

Use `pg_dump`, `pg_restore` e `psql` do container PostgreSQL. A versão major deve coincidir com a do servidor. Não instale ferramentas no host apenas para executar este fluxo.

O destino aprovado é `/var/backups/ircenter/database/app/daily`, fora do checkout, com componentes sem symlink, owner/group `ircenter-backup:ircenter-backup`, diretórios `0700` e arquivos `0600`. Confirme espaço livre antes de iniciar. O nome segue `ircenter-finance-fiscal-YYYYMMDDTHHMMSSZ.dump`.

Backups mantidos ou transportados fora dessa camada local devem usar a criptografia `age` e o cofre definidos na política. Não habilite retenção automática antes de validar backup, criptografia e cópia externa. A referência inicial é 14 dias para daily, 90 para weekly e 730 para monthly.

## Backup

1. Confirme health, versões, banco alvo, espaço, diretório e permissões.
2. Defina antecipadamente um timestamp UTC e os paths exatos do arquivo final e de um `.partial` no mesmo diretório.
3. Como root operacional, use `umask 077` e execute no serviço `postgres`:

```text
pg_dump --format=custom --no-owner --no-privileges \
  -U "$POSTGRES_USER" -d ircenter_finance
```

Redirecione stdout para o `.partial`. O shell que expande `POSTGRES_USER` deve ser o do container, não o host. Somente após exit code zero e arquivo não vazio, mova atomicamente para o nome final, aplique owner/group e `0600`.

Calcule SHA-256 e preserve um sidecar `.dump.sha256` com o mesmo owner/mode. Execute `pg_restore --list` contra o dump e registre apenas contagens e nomes de objetos, nunca conteúdo de linhas.

## Restore temporário obrigatório

O backup só é válido depois de restaurado. Use um nome literal no padrão `ircenter_restore_verify_YYYYMMDDtHHMMSSz`. Antes de criar ou remover, confirme que ele é diferente de `ircenter_finance`, do banco Core e de `$POSTGRES_DB`.

1. Confirme que o nome ainda não existe.
2. Crie o database temporário a partir de `template0`.
3. Passe o dump por stdin para o container e execute:

```text
pg_restore --exit-on-error --no-owner --no-privileges \
  -U "$POSTGRES_USER" -d NOME_TEMPORARIO
```

4. Compare origem e restore sem exibir dados: lista e total de tabelas, `COUNT(*)` de cada tabela, migrations, índices, constraints, foreign keys, sequences e extensões.
5. Qualquer diferença ou erro invalida o backup. Não desabilite constraints.
6. Depois de todas as comparações, valide novamente o nome literal e remova somente o database temporário com `dropdb`. Confirme sua ausência.
7. Preserve o dump, SHA-256 e uma evidência sanitizada `.restore-verified.txt` em `0600`.

O restore nunca pode sobrescrever database existente, alterar `.env`, apontar a aplicação para o database temporário ou usar nome calculado por wildcard. Em caso de dúvida, não execute `dropdb`.

## Evidências e liberação de migration

Registre: horário UTC, versões, nome sanitizado, tamanho, SHA-256, exit codes, entradas do catálogo, comparações estruturais e de contagens, remoção do temporário, espaço e health antes/depois. Inspecione logs novos e diferencie falhas da aplicação de consultas administrativas da validação.

Uma migration só pode prosseguir em nova janela explícita quando `pg_dump`, arquivo/permissões, SHA-256, `pg_restore --list`, restore isolado, todas as comparações, cleanup e health estiverem aprovados. Este procedimento não autoriza deploy, migration, alteração de feature flag ou operação fiscal.

## Rollback e riscos

Backup lógico não modifica a aplicação e normalmente não exige indisponibilidade. Se dump, catálogo ou restore falhar, preserve a produção, remova apenas o database temporário após confirmar o nome e investigue. Nunca apague o último backup validado, restaure sobre produção, copie credenciais para `/tmp` ou coloque dumps no Git.
