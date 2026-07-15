# Data Model: TPanel MVP

Modelo conceitual para persistencia do MVP. Nomes de entidades, tabelas e campos seguem ingles e camelCase para compatibilidade com as regras MySQL do projeto. Este documento nao cria SQL; scripts ficam para a etapa de implementacao.

## Entity: UserRole

Representa o papel aplicado a uma identidade autenticada pelo Apache.

| Field | Type | Constraints | Notes |
|-------|------|-------------|-------|
| id | BIGINT | PK, auto-generated | Identificador interno |
| roleName | enum | NOT NULL, unique, values: ADMINISTRATOR, MONITOR | Papel funcional |
| description | string | NOT NULL | Descricao operacional |
| canRunAdministrativeAction | boolean | NOT NULL | True apenas para Administrador |
| canAcknowledgeAlert | boolean | NOT NULL | True para Administrador e Monitor |
| canCommentEvent | boolean | NOT NULL | True para Administrador e Monitor |
| createdAt | datetime | NOT NULL | Criacao |
| updatedAt | datetime | NOT NULL | Ultima atualizacao |

### Relationships

- `UserRole` 1:N `AuthenticatedUser` via `idUserRole`.

## Entity: AuthenticatedUser

Representa uma identidade autenticada pelo Apache conhecida pelo TPanel para autorizacao e auditoria.

| Field | Type | Constraints | Notes |
|-------|------|-------------|-------|
| id | BIGINT | PK, auto-generated | Identificador interno |
| idUserRole | BIGINT | FK, NOT NULL | Papel aplicado |
| externalUsername | string | NOT NULL, unique | Identidade recebida do Apache |
| displayName | string | NULL | Nome amigavel opcional |
| isActive | boolean | NOT NULL | Permite desativar acesso no painel sem alterar Apache |
| lastSeenAt | datetime | NULL | Ultimo acesso conhecido |
| createdAt | datetime | NOT NULL | Criacao |
| updatedAt | datetime | NOT NULL | Ultima atualizacao |

### Relationships

- `AuthenticatedUser` N:1 `UserRole` via `idUserRole`.
- `AuthenticatedUser` 1:N `AuditRecord` via `idActorUser`.
- `AuthenticatedUser` 1:N `AlertAcknowledgement` via `idActorUser`.
- `AuthenticatedUser` 1:N `EventComment` via `idActorUser`.

## Entity: ServerHealthSummary

Snapshot consolidado da saude atual do servidor para dashboard e telas de resumo.

| Field | Type | Constraints | Notes |
|-------|------|-------------|-------|
| id | BIGINT | PK, auto-generated | Identificador interno |
| healthStatus | enum | NOT NULL, values: NORMAL, WARNING, CRITICAL, UNAVAILABLE | Estado geral |
| hostname | string | NOT NULL | Host observado |
| uptimeSeconds | BIGINT | NULL | Uptime quando disponivel |
| loadAverage | string | NULL | Load resumido |
| collectedAt | datetime | NOT NULL | Momento da coleta |
| freshnessStatus | enum | NOT NULL, values: FRESH, STALE, UNKNOWN | Qualidade temporal dos dados |
| createdAt | datetime | NOT NULL | Criacao |
| updatedAt | datetime | NOT NULL | Ultima atualizacao |

### Relationships

- `ServerHealthSummary` 1:N `MetricReading` via `idServerHealthSummary` quando a leitura pertence ao snapshot.

### Freshness Policy

- `FRESH`: snapshot mais recente dentro do limite da categoria dominante ou dentro de 120 segundos quando o resumo agrega varias categorias.
- `STALE`: snapshot existente, mas mais antigo que o limite definido para o dashboard.
- `UNKNOWN`: nenhum snapshot valido existe, a coleta ainda nao executou, ou todas as fontes necessarias falharam sem leitura anterior.

## Entity: MetricReading

Leitura pontual de metrica atual ou historica.

| Field | Type | Constraints | Notes |
|-------|------|-------------|-------|
| id | BIGINT | PK, auto-generated | Identificador interno |
| idServerHealthSummary | BIGINT | FK, NULL | Snapshot associado quando aplicavel |
| metricCategory | enum | NOT NULL | SYSTEM, CPU, MEMORY, STORAGE, DISK_HEALTH, RAID, NETWORK, SERVICE, PROCESS, LOG, SECURITY, SENSOR, SCHEDULE |
| metricName | string | NOT NULL | Nome estavel da metrica |
| metricValue | decimal/string/json | NOT NULL | Valor normalizado ou composto |
| unit | string | NULL | Unidade quando aplicavel |
| severity | enum | NOT NULL, values: NORMAL, WARNING, CRITICAL, UNAVAILABLE | Estado da metrica |
| source | string | NOT NULL | Origem logica da leitura |
| collectedAt | datetime | NOT NULL | Momento da coleta |
| expiresAt | datetime | NULL | Data planejada para retencao |
| createdAt | datetime | NOT NULL | Criacao |

### Relationships

- `MetricReading` N:1 `ServerHealthSummary` via `idServerHealthSummary`.
- `MetricReading` 1:N `Alert` via `idMetricReading` quando uma leitura gera alerta.

### Collection and Retention Policy

O MVP persiste leituras brutas e nao cria tabelas de agregacao. Graficos historicos consultam janelas limitadas de `MetricReading` e podem aplicar downsampling em memoria apenas para exibicao.

| metricCategory | Initial interval | Fresh threshold | Stale threshold |
|----------------|------------------|-----------------|-----------------|
| SYSTEM | 60 seconds | <= 120 seconds | > 120 seconds |
| CPU | 30 seconds | <= 90 seconds | > 90 seconds |
| MEMORY | 30 seconds | <= 90 seconds | > 90 seconds |
| STORAGE | 300 seconds | <= 900 seconds | > 900 seconds |
| DISK_HEALTH | 900 seconds | <= 3600 seconds | > 3600 seconds |
| RAID | 300 seconds | <= 900 seconds | > 900 seconds |
| NETWORK | 30 seconds | <= 90 seconds | > 90 seconds |
| SERVICE | 60 seconds | <= 180 seconds | > 180 seconds |
| PROCESS | 30 seconds | <= 90 seconds | > 90 seconds |
| LOG | 300 seconds | <= 900 seconds | > 900 seconds |
| SECURITY | 300 seconds | <= 900 seconds | > 900 seconds |
| SENSOR | 60 seconds | <= 180 seconds | > 180 seconds |
| SCHEDULE | 300 seconds | <= 900 seconds | > 900 seconds |

Retention defaults to 90 days and is configurable. For retained historical metrics, `expiresAt` should be calculated as `collectedAt + retentionDays`; current-only or diagnostic records may use `expiresAt = NULL` only when retention cleanup intentionally does not apply.

## Entity: Alert

Condição que requer atenção operacional.

| Field | Type | Constraints | Notes |
|-------|------|-------------|-------|
| id | BIGINT | PK, auto-generated | Identificador interno |
| idMetricReading | BIGINT | FK, NULL | Leitura de origem quando aplicavel |
| alertSource | string | NOT NULL | Area ou modulo |
| severity | enum | NOT NULL, values: INFO, WARNING, CRITICAL | Severidade |
| title | string | NOT NULL | Titulo operacional |
| message | text | NOT NULL | Mensagem sem segredos |
| status | enum | NOT NULL, values: OPEN, ACKNOWLEDGED, RESOLVED, DISMISSED | Estado do alerta |
| openedAt | datetime | NOT NULL | Abertura |
| resolvedAt | datetime | NULL | Resolucao quando aplicavel |
| createdAt | datetime | NOT NULL | Criacao |
| updatedAt | datetime | NOT NULL | Ultima atualizacao |

### Relationships

- `Alert` N:1 `MetricReading` via `idMetricReading`.
- `Alert` 1:N `AlertAcknowledgement` via `idAlert`.
- `Alert` 1:N `EventComment` via `idAlert`.
- `Alert` 1:N `NotificationEvent` via `idAlert`.

### State Transitions

```text
OPEN -> ACKNOWLEDGED -> RESOLVED
OPEN -> RESOLVED
OPEN -> DISMISSED
ACKNOWLEDGED -> DISMISSED
```

## Entity: AlertAcknowledgement

Registro de reconhecimento de alerta por Administrador ou Monitor.

| Field | Type | Constraints | Notes |
|-------|------|-------------|-------|
| id | BIGINT | PK, auto-generated | Identificador interno |
| idAlert | BIGINT | FK, NOT NULL | Alerta reconhecido |
| idActorUser | BIGINT | FK, NOT NULL | Usuario que reconheceu |
| acknowledgementNote | text | NULL | Observacao opcional |
| acknowledgedAt | datetime | NOT NULL | Momento do reconhecimento |
| createdAt | datetime | NOT NULL | Criacao |

### Relationships

- `AlertAcknowledgement` N:1 `Alert` via `idAlert`.
- `AlertAcknowledgement` N:1 `AuthenticatedUser` via `idActorUser`.

## Entity: EventComment

Comentario operacional associado a alerta, evento ou auditoria.

| Field | Type | Constraints | Notes |
|-------|------|-------------|-------|
| id | BIGINT | PK, auto-generated | Identificador interno |
| idAlert | BIGINT | FK, NULL | Alerta comentado quando aplicavel |
| idAuditRecord | BIGINT | FK, NULL | Auditoria comentada quando aplicavel |
| idActorUser | BIGINT | FK, NOT NULL | Autor |
| commentText | text | NOT NULL | Comentario sem segredos |
| createdAt | datetime | NOT NULL | Criacao |
| updatedAt | datetime | NOT NULL | Ultima edicao quando permitida |

### Relationships

- `EventComment` N:1 `Alert` via `idAlert`.
- `EventComment` N:1 `AuditRecord` via `idAuditRecord`.
- `EventComment` N:1 `AuthenticatedUser` via `idActorUser`.

## Entity: AdministrativeAction

Acao administrativa controlada disponivel no catalogo autorizado.

| Field | Type | Constraints | Notes |
|-------|------|-------------|-------|
| id | BIGINT | PK, auto-generated | Identificador interno |
| actionKey | string | NOT NULL, unique | Chave estavel da acao |
| displayName | string | NOT NULL | Nome exibido |
| description | text | NOT NULL | Descricao operacional |
| targetType | enum | NOT NULL | SERVICE, CONTAINER, SCHEDULE, SYSTEM, NOTIFICATION, OTHER |
| isEnabled | boolean | NOT NULL | Habilitacao operacional |
| timeoutSeconds | integer | NOT NULL | Timeout maximo |
| requiresConfirmation | boolean | NOT NULL | Evita execucao acidental |
| createdAt | datetime | NOT NULL | Criacao |
| updatedAt | datetime | NOT NULL | Ultima atualizacao |

### Relationships

- `AdministrativeAction` 1:N `CommandMapping` via `idAdministrativeAction`.
- `AdministrativeAction` 1:N `AuditRecord` via `idAdministrativeAction`.

## Entity: CommandMapping

Mapeamento entre uma acao autorizada e uma operacao executavel pelo executor de comandos.

| Field | Type | Constraints | Notes |
|-------|------|-------------|-------|
| id | BIGINT | PK, auto-generated | Identificador interno |
| idAdministrativeAction | BIGINT | FK, NOT NULL | Acao associada |
| commandKey | string | NOT NULL, unique | Identificador interno do comando |
| executablePath | string | NOT NULL | Caminho permitido ou alias operacional |
| allowedParametersSchema | json | NOT NULL | Regras de validacao de parametros |
| runAsUser | string | NOT NULL | Usuario efetivo esperado |
| isEnabled | boolean | NOT NULL | Habilitacao |
| createdAt | datetime | NOT NULL | Criacao |
| updatedAt | datetime | NOT NULL | Ultima atualizacao |

### Relationships

- `CommandMapping` N:1 `AdministrativeAction` via `idAdministrativeAction`.

## Entity: CommandExecutionRequest

Registro de idempotencia para execucoes administrativas confirmadas.

| Field | Type | Constraints | Notes |
|-------|------|-------------|-------|
| id | BIGINT | PK, auto-generated | Identificador interno |
| requestId | string | NOT NULL, unique | Identificador enviado pela UI para retry seguro |
| actionKey | string | NOT NULL | Acao administrativa associada |
| requestFingerprint | string | NOT NULL | SHA-256 dos detalhes autorizados da execucao |
| resultStatus | enum | NOT NULL, values: IN_PROGRESS, SUCCESS, DENIED, FAILED, TIMED_OUT | Estado idempotente da tentativa |
| exitCode | integer | NULL | Codigo de saida quando aplicavel |
| stdoutSummary | text | NULL | Resumo sanitizado de stdout |
| stderrSummary | text | NULL | Resumo sanitizado de stderr |
| failureReason | text | NULL | Motivo sanitizado quando aplicavel |
| expiresAt | datetime | NOT NULL | Fim da janela idempotente |
| createdAt | datetime | NOT NULL | Criacao/reserva |
| updatedAt | datetime | NOT NULL | Ultima atualizacao |

### Idempotency Policy

Administrative command execution uses an initial 15-minute idempotency window. Within that window:

- first submission reserves `requestId` with `IN_PROGRESS`;
- retry with the same `requestId` and same fingerprint returns the previous terminal result;
- retry while the first execution is still `IN_PROGRESS` is denied safely without reexecution;
- reuse of the same `requestId` with different command details is denied safely;
- after expiration, the same `requestId` may be reserved again only after the expired record is discarded.

## Entity: AuditRecord

Registro imutavel de tentativa administrativa ou evento sensivel.

| Field | Type | Constraints | Notes |
|-------|------|-------------|-------|
| id | BIGINT | PK, auto-generated | Identificador interno |
| idActorUser | BIGINT | FK, NOT NULL | Usuario associado |
| idAdministrativeAction | BIGINT | FK, NULL | Acao quando aplicavel |
| auditType | enum | NOT NULL | ADMIN_ACTION, DENIED_ACTION, ALERT_ACK, EVENT_COMMENT, NOTIFICATION, AUTHORIZATION |
| actionKey | string | NULL | Chave da acao solicitada |
| validatedParameters | json | NULL | Parametros validados, sem segredos |
| resultStatus | enum | NOT NULL, values: SUCCESS, DENIED, FAILED, TIMED_OUT, SKIPPED | Resultado |
| exitCode | integer | NULL | Codigo de saida quando aplicavel |
| failureReason | text | NULL | Motivo sanitizado |
| requestId | string | NOT NULL | Identificador para idempotencia/rastreio |
| occurredAt | datetime | NOT NULL | Momento do evento |
| createdAt | datetime | NOT NULL | Criacao |

### Relationships

- `AuditRecord` N:1 `AuthenticatedUser` via `idActorUser`.
- `AuditRecord` N:1 `AdministrativeAction` via `idAdministrativeAction`.
- `AuditRecord` 1:N `EventComment` via `idAuditRecord`.

## Entity: ConfigurationModel

Referencia versionada de configuracoes esperadas e seus modelos.

| Field | Type | Constraints | Notes |
|-------|------|-------------|-------|
| id | BIGINT | PK, auto-generated | Identificador interno |
| configKey | string | NOT NULL, unique | Nome logico |
| modelPath | string | NOT NULL | Caminho do `.model` versionado |
| runtimePath | string | NOT NULL | Caminho esperado do arquivo real ignorado |
| containsSecrets | boolean | NOT NULL | Indica necessidade de protecao |
| isRequired | boolean | NOT NULL | Obrigatoria para iniciar ou opcional |
| createdAt | datetime | NOT NULL | Criacao |
| updatedAt | datetime | NOT NULL | Ultima atualizacao |

## Entity: NotificationEvent

Evento preparado ou enviado para notificacao externa.

| Field | Type | Constraints | Notes |
|-------|------|-------------|-------|
| id | BIGINT | PK, auto-generated | Identificador interno |
| idAlert | BIGINT | FK, NULL | Alerta associado quando aplicavel |
| sender | string | NOT NULL, max 20 | Identificador para NotiCLI |
| category | string | NOT NULL | Categoria de roteamento |
| priority | enum | NOT NULL, values: HIGH, NORMAL, LOW | Prioridade |
| title | string | NOT NULL | Titulo sem segredos |
| message | text | NOT NULL | Mensagem sem segredos |
| deliveryStatus | enum | NOT NULL, values: PENDING, SENT, FAILED, SKIPPED | Estado de envio |
| exitCode | integer | NULL | Codigo de saida quando aplicavel |
| failureReason | text | NULL | Motivo sanitizado |
| createdAt | datetime | NOT NULL | Criacao |
| sentAt | datetime | NULL | Momento de envio aceito |
| updatedAt | datetime | NOT NULL | Ultima atualizacao |

### Relationships

- `NotificationEvent` N:1 `Alert` via `idAlert`.

### State Transitions

```text
PENDING -> SENT
PENDING -> FAILED
PENDING -> SKIPPED
FAILED -> PENDING
```
