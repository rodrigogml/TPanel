# Contracts: Notification Events

Contrato para eventos que podem ser enviados ao NotiCLI. A integracao e opcional no MVP, mas o formato deve permanecer consistente quando habilitada.

## NotiCLI Send Event

**Direction**: TPanel -> NotiCLI process
**Auth**: Local process permissions; NotiCLI owns provider credentials and routes

### Event Fields

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| sender | string | yes | max 20 chars; default logical sender: TPanel |
| category | string | yes | stable routing category |
| priority | enum | yes | HIGH, NORMAL, LOW |
| title | string | yes | sanitized; no secrets |
| message | string | yes | sanitized; no secrets |
| attachmentPath | string | no | readable path only when explicitly allowed |

### Suggested Categories

| Category | Meaning |
|----------|---------|
| server | Overall server health |
| service | systemd service state |
| docker | Docker or container state |
| disk | Filesystem or SMART issue |
| raid | RAID degradation or sync issue |
| security | SSH, firewall or update issue |
| schedule | cron or timer issue |
| audit | Administrative action result |

### Result Record

| Field | Type | Description |
|-------|------|-------------|
| deliveryStatus | enum | SENT, FAILED, SKIPPED |
| exitCode | integer/null | NotiCLI exit code when invoked |
| failureReason | string/null | Sanitized diagnostic |
| sentAt | datetime/null | Time accepted by NotiCLI when successful |

### Error Handling

| Exit Category | TPanel Behavior |
|---------------|-----------------|
| success | Record SENT and link to source alert/action |
| invalid_input | Record FAILED and flag configuration/event shape issue |
| missing_config | Record FAILED and show admin-visible configuration warning |
| invalid_config | Record FAILED and show admin-visible configuration warning |
| delivery_failure | Record FAILED while preserving original alert state |
| internal_error | Record FAILED and include sanitized diagnostic |
