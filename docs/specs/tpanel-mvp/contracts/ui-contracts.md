# Contracts: TPanel MVP UI Payloads

Contratos internos entre controllers/services e views. O MVP nao expoe API REST publica, mas estes contratos definem formatos de dados que a UI deve consumir.

## Dashboard Summary Payload

**Direction**: Controller -> UI
**Auth**: Apache-authenticated user mapped to Administrador or Monitor

### Fields

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| requestId | string | yes | non-empty |
| currentUser | object | yes | contains username and role |
| healthStatus | enum | yes | NORMAL, WARNING, CRITICAL, UNAVAILABLE |
| collectedAt | datetime | yes | ISO-like display-safe timestamp |
| freshnessStatus | enum | yes | FRESH, STALE, UNKNOWN |
| cards | list | yes | one or more status cards |
| alerts | list | yes | zero or more alert summaries |

### Status Card

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| key | string | yes | stable kebab-case UI key |
| title | string | yes | display label |
| severity | enum | yes | NORMAL, WARNING, CRITICAL, UNAVAILABLE |
| primaryValue | string | yes | sanitized display text |
| secondaryValue | string | no | sanitized display text |
| updatedAt | datetime | no | present when known |

### Error States

| Code | Description |
|------|-------------|
| DATA_UNAVAILABLE | Source exists but could not be read |
| SOURCE_NOT_INSTALLED | Optional capability is not present |
| PERMISSION_DENIED | Source exists but lacks read permission |
| STALE_DATA | Last known data is older than freshness policy |

## Administrative Action Form

**Direction**: UI -> Controller
**Auth**: Administrador only

### Fields

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| requestId | string | yes | unique per confirmation attempt |
| actionKey | string | yes | exists in authorized catalog |
| targetKey | string | conditional | required when action targets service/container/timer |
| parameters | object | no | must match action parameter schema |
| confirmationAccepted | boolean | yes | must be true for destructive or restart actions |

### Result Payload

| Field | Type | Description |
|-------|------|-------------|
| requestId | string | Correlates request, audit and UI result |
| resultStatus | enum | SUCCESS, DENIED, FAILED, TIMED_OUT |
| message | string | Sanitized user-facing result |
| auditRecordId | integer | Audit reference when record was created |
| exitCode | integer/null | Command exit code when applicable |

## Alert Acknowledgement Form

**Direction**: UI -> Controller
**Auth**: Administrador or Monitor

### Fields

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| requestId | string | yes | unique per acknowledgement attempt |
| alertId | integer | yes | existing alert |
| acknowledgementNote | string | no | sanitized; max length defined in implementation |

### Result Payload

| Field | Type | Description |
|-------|------|-------------|
| requestId | string | Correlates request and audit |
| alertStatus | enum | OPEN, ACKNOWLEDGED, RESOLVED, DISMISSED |
| auditRecordId | integer | Audit reference |

## Event Comment Form

**Direction**: UI -> Controller
**Auth**: Administrador or Monitor

### Fields

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| requestId | string | yes | unique per comment attempt |
| targetType | enum | yes | ALERT or AUDIT_RECORD |
| targetId | integer | yes | existing target |
| commentText | string | yes | sanitized; non-empty; max length defined in implementation |

### Result Payload

| Field | Type | Description |
|-------|------|-------------|
| requestId | string | Correlates request and audit |
| commentId | integer | Created comment reference |
| auditRecordId | integer | Audit reference |
