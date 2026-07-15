# Contracts: Command Executor

Contrato interno para execucao de acoes administrativas autorizadas. Este contrato nao permite comandos arbitrarios.

## Authorized Command Request

**Direction**: Service -> Command Executor
**Auth**: Administrador authorized by application policy

### Request

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| requestId | string | yes | unique per confirmed action |
| actorUsername | string | yes | authenticated identity from Apache |
| actionKey | string | yes | must exist in enabled catalog |
| commandKey | string | yes | must map to actionKey |
| parameters | object | no | must match allowedParametersSchema |
| timeoutSeconds | integer | yes | positive and within configured max |
| auditContext | object | yes | contains source page and target labels |

### Response

| Field | Type | Description |
|-------|------|-------------|
| requestId | string | Correlation ID |
| resultStatus | enum | SUCCESS, DENIED, FAILED, TIMED_OUT |
| exitCode | integer/null | Exit code when process completed |
| stdoutSummary | string/null | Sanitized summary only |
| stderrSummary | string/null | Sanitized summary only |
| failureReason | string/null | Sanitized reason |
| startedAt | datetime | Execution start |
| finishedAt | datetime/null | Execution end when available |

### Rejection Rules

| Code | Description |
|------|-------------|
| ACTION_NOT_AUTHORIZED | actionKey is not enabled or not mapped |
| ROLE_NOT_ALLOWED | Actor role cannot execute administrative actions |
| PARAMETER_INVALID | Parameter failed validation before execution |
| TIMEOUT_EXCEEDED | Execution did not finish in time |
| EXECUTION_FAILED | Command returned failure or could not be invoked |

## Parameter Validation

Parameter validation MUST run before command execution. Invalid requests MUST return `PARAMETER_INVALID` and MUST NOT produce a partially validated command request for execution.

The initial validator supports the schema forms used by `config/commands.php.model`:

| Schema type | Rules |
|-------------|-------|
| string | Value must be a non-empty string, must not exceed `maxLength` when configured, and must match `pattern` when configured |
| enum | Value must be a string and must exactly match one of the configured `values` |

Validation rejects:

- missing parameters required by the catalog entry;
- extra parameters not present in `allowedParametersSchema`;
- non-string values for `string` and `enum` schemas;
- malformed values such as shell separators or values outside the configured allowlist pattern;
- enum values outside the configured allowlist.

## Execution Behavior

The executor accepts only an `Authorized Command Request` object. Callers MUST complete catalog lookup, role authorization and parameter validation before constructing this request.

Execution rules:

- invoke the approved executable path with an argument array, not by concatenating a shell command string;
- append only validated parameter values to the process argument array;
- close stdin and capture stdout/stderr separately;
- enforce `timeoutSeconds` from the authorized request;
- return `TIMED_OUT` with a clear failure reason when the process exceeds timeout;
- return `FAILED` with exit code and sanitized summaries when the process exits non-zero or cannot start;
- return `SUCCESS` only for exit code `0`;
- sanitize stdout, stderr and failure details before presenting or persisting summaries.

## Idempotency Behavior

Administrative command execution uses `requestId` as the idempotency key with an initial 15-minute window.

| Situation | Behavior |
|-----------|----------|
| First submission | Reserve `requestId` as `IN_PROGRESS`, execute once, then store terminal result |
| Retry after terminal result with same request fingerprint | Return the stored result without reexecution |
| Retry while original request is `IN_PROGRESS` | Return `DENIED` with a safe duplicate-in-progress reason |
| Reuse of `requestId` with different command details | Return `DENIED` and do not execute |
| Expired key | Allow a new reservation after the expired record is discarded |

## Authorized Command Catalog Entry

**Direction**: Configuration/Persistence -> Service

### Fields

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| actionKey | string | yes | unique stable key |
| commandKey | string | yes | unique stable key |
| executablePath | string | yes | approved path or alias only |
| allowedParametersSchema | object | yes | explicit validation rules |
| timeoutSeconds | integer | yes | positive |
| requiresConfirmation | boolean | yes | true for restart/state-changing actions |
| enabled | boolean | yes | disabled entries cannot execute |

## Initial MVP Authorized Catalog

Catalogo inicial versionado em `config/commands.php.model`. Acoes que mudam estado ficam mapeadas, mas `enabled=false` ate que os wrappers em `scripts/system/` e sudoers restritos sejam implementados e validados.

| actionKey | targetType | commandKey | Initial enabled | Confirmation | Timeout | Parameters |
|-----------|------------|------------|-----------------|--------------|---------|------------|
| service.status | SERVICE | systemctl-status | true | false | 5s | `serviceName` allowlist pattern |
| service.restart | SERVICE | systemctl-restart | false | true | 30s | `serviceName` allowlist pattern |
| service.reload | SERVICE | systemctl-reload | false | true | 30s | `serviceName` allowlist pattern |
| docker.container.status | CONTAINER | docker-container-status | true | false | 5s | `containerName` allowlist pattern |
| docker.container.restart | CONTAINER | docker-container-restart | false | true | 45s | `containerName` allowlist pattern |
| schedule.timer.status | SCHEDULE | systemctl-timer-status | true | false | 5s | `timerName` ending in `.timer` |
| schedule.timer.restart | SCHEDULE | systemctl-timer-restart | false | true | 30s | `timerName` ending in `.timer` |
| monitoring.collect.once | SYSTEM | tpanel-collector-once | false | true | 60s | `metricCategory` enum |
| notification.test | NOTIFICATION | noticli-send-test | false | true | 10s | `category` enum, `priority` enum |

## Explicitly Excluded Actions

The MVP catalog MUST NOT include:

- free-form shell command execution;
- arbitrary executable path input;
- package installation or operating system upgrades;
- Linux user/group management;
- firewall mutation;
- arbitrary file editing;
- interactive database shells;
- destructive storage, partition, RAID reshape or filesystem mutation.
