# TPanel Backup and Restore Procedure

Procedimento operacional inicial para backup e restore do banco MySQL do TPanel. Este documento nao contem credenciais reais; comandos que exigem senha devem usar arquivo local protegido, certificado do ambiente ou mecanismo operacional equivalente.

## Escopo

Backup obrigatorio:

- auditoria (`auditRecord`);
- usuarios e papeis (`authenticatedUser`, `userRole`);
- acoes autorizadas e mapeamentos (`administrativeAction`, `commandMapping`);
- alertas, reconhecimentos e comentarios (`alert`, `alertAcknowledgement`, `eventComment`);
- configuracoes versionadas no banco (`configurationModel`);
- eventos de notificacao (`notificationEvent`).

Backup sujeito a politica de retencao:

- metricas historicas (`metricReading`);
- snapshots de saude (`serverHealthSummary`).

Arquivos reais de configuracao em `config/*.php` ficam fora do Git e devem ser tratados como segredo operacional. Eles devem ser copiados por mecanismo seguro separado do dump versionavel, com permissao restrita e nunca incluidos em artefatos publicos.

## Periodicidade Minima

| Dados | Periodicidade minima | Retencao minima recomendada | Observacao |
|-------|----------------------|-----------------------------|------------|
| Auditoria, usuarios, papeis, acoes, alertas e comentarios | Diario | 30 dias de backups diarios e 12 backups mensais | Historico operacional importante. |
| Configuracoes reais locais (`config/*.php`) | A cada alteracao e diario quando houver automacao | 30 dias | Contem segredos; armazenar cifrado ou em cofre local. |
| Metricas historicas | Diario | Igual ou menor que `monitoring.retentionDays`, padrao 90 dias | Nao ha obrigacao de restaurar metricas ja expiradas. |
| Backups de teste de restore | Semanal | Ultimo resultado validado | Deve usar ambiente temporario sem dados sensiveis. |

## Backup MySQL

Use `mysqldump` com transacao consistente e sem registrar credenciais no historico de shell.

```bash
install -m 700 -d /var/backups/tpanel

mysqldump \
  --single-transaction \
  --routines \
  --triggers \
  --events \
  --set-gtid-purged=OFF \
  --result-file="/var/backups/tpanel/tpanel-$(date +%Y%m%d-%H%M%S).sql" \
  tpanel

chmod 600 /var/backups/tpanel/tpanel-*.sql
```

Quando o ambiente exigir usuario/senha, use um arquivo local com permissao `600` e passe por `--defaults-extra-file=/path/seguro/mysql-client.cnf`. Nao coloque senha diretamente no comando.

## Restore Em Ambiente De Teste

Nunca valide restore sobrescrevendo o schema de producao. Use schema temporario.

```bash
RESTORE_SCHEMA="tpanel_restore_check"
BACKUP_FILE="/var/backups/tpanel/tpanel-YYYYMMDD-HHMMSS.sql"

mysql -e "DROP DATABASE IF EXISTS ${RESTORE_SCHEMA}; CREATE DATABASE ${RESTORE_SCHEMA} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql "${RESTORE_SCHEMA}" < "${BACKUP_FILE}"
mysql -e "SELECT COUNT(*) AS tableCount FROM information_schema.tables WHERE table_schema = '${RESTORE_SCHEMA}';"
mysql -e "SELECT COUNT(*) AS auditCount FROM ${RESTORE_SCHEMA}.auditRecord;"
mysql -e "SELECT COUNT(*) AS metricCount FROM ${RESTORE_SCHEMA}.metricReading;"
mysql -e "DROP DATABASE ${RESTORE_SCHEMA};"
```

Critérios de sucesso:

- restore conclui sem erro de SQL;
- schema restaurado contem todas as tabelas esperadas do init atual;
- consultas essenciais de auditoria e metricas executam sem erro;
- amostra de dados nao contem segredos em texto claro;
- schema temporario e removido ao final da validacao.

## Restore Operacional

Antes de restaurar em ambiente real:

1. Interrompa o Apache ou coloque o TPanel em manutencao para evitar escrita concorrente.
2. Faça backup final do estado atual antes de qualquer restore.
3. Restaure primeiro em schema temporario e valide contagem de tabelas e consultas essenciais.
4. Restaure no schema `tpanel` apenas depois da validacao.
5. Reaplique arquivos reais de `config/*.php` por canal seguro, quando necessario.
6. Execute `composer check` e smoke checks de login, auditoria e dashboard.
7. Registre o restore em auditoria operacional externa quando o TPanel ainda nao estiver disponivel.

## Politica Para Metricas Expiradas

Metricas com `expiresAt` anterior ou igual ao momento do purge podem ser removidas conforme `monitoring.retentionDays`. Backups nao precisam preservar metricas alem da janela configurada. Em restore, metricas expiradas podem ser purgadas apos validacao para reduzir volume.

## Segurança

- Arquivos de backup devem ter permissao `600`.
- Diretorios de backup devem ter permissao `700`.
- Dumps com dados reais nunca devem ser commitados.
- Backups contendo `config/*.php` devem ser cifrados ou armazenados em cofre/local protegido.
- Validacoes documentadas no repositorio devem usar somente bancos temporarios e dados sinteticos sem segredos.
