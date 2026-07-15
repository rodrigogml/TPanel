# Implementation Plan: TPanel MVP

**Feature**: `tpanel-mvp` | **Date**: 2026-07-14 | **Spec**: [spec.md](spec.md)

## Summary

Implementar o MVP do TPanel como portal PHP monolitico sobre Apache para monitoramento e administracao segura de um servidor Linux Debian 13. A abordagem tecnica preserva camadas internas explicitas, MySQL para auditoria/metricas/configuracoes aplicaveis, executor de comandos por catalogo autorizado, UI responsiva premium e integracao futura/oplugavel com NotiCLI.

## Technical Context

**Language/Version**: PHP 8.x alvo do Debian 13, shell scripts Linux controlados, JavaScript/CSS para UI responsiva
**Primary Dependencies**: Apache, PHP runtime, MySQL, sudo, systemd tools, Docker CLI when available, smartmontools when available, lm-sensors when available, NotiCLI optional
**Storage**: MySQL for audit records, metric history, alerts, comments, configuration references and notification records; filesystem for local config files and `.model` examples
**Testing**: PHP unit/service tests where applicable, command validation tests, shell script smoke tests, manual quickstart validation until automated harness exists
**Target Platform**: Debian 13 server running Apache
**Project Type**: PHP web portal / server administration panel
**Performance Goals**: Dashboard visible in 2 seconds p95 on target local server; user identifies health in 30 seconds; no expensive polling without explicit interval
**Constraints**: No REST API in this phase; no arbitrary command execution; Apache owns authentication; secrets stay out of Git; Monitor cannot change server state; metric retention configurable with default 90 days
**Scale/Scope**: Single-server MVP, 2 user roles, local/open source resources, future modular expansion

## Constitution Check

*GATE: Deve passar antes do Phase 0. Rechecar apos Phase 1.*

| Principio | Status | Notas |
|-----------|--------|-------|
| Security Before Capability | PASS | Executor por catalogo autorizado, validacao, timeout, auditoria e sudo restrito sao requisitos centrais. |
| Modular Monolith With Clear Layers | PASS | Plano preserva UI, controllers, services, command executor e system scripts. |
| Auditable Operations And Observable Failures | PASS | Auditoria, logs, resultados de comandos e notificacoes sao modelados explicitamente. |
| Local, Portable, And Repository-Safe Configuration | PASS | MySQL/local config, `.model`, segredos fora do Git e NotiCLI com config propria. |
| Premium, Responsive, And Efficient Interface | PASS | UI responsiva, temas e validacao de viewport estao em spec/quickstart. |

## Phase 0 - Research Summary

Pesquisa documentada em [research.md](research.md).

Decisoes principais:

- Monolito PHP em camadas, sem API REST publica no MVP.
- Apache autentica; TPanel autoriza por papel e capacidade.
- Administrador executa acoes autorizadas; Monitor reconhece alertas e comenta eventos sem alterar servidor.
- Comandos administrativos passam por catalogo autorizado, validacao e timeout.
- MySQL persiste auditoria, alertas, metricas historicas, comentarios, configuracoes aplicaveis e notificacoes.
- Retencao de metricas configuravel com padrao de 90 dias.
- Contratos internos documentam payloads de UI, executor de comandos e notificacoes.
- NotiCLI permanece responsavel por rotas e segredos de provedores.

## Phase 1 - Design Summary

Artefatos de design:

- Modelo de dados: [data-model.md](data-model.md)
- Contratos UI: [contracts/ui-contracts.md](contracts/ui-contracts.md)
- Contrato executor: [contracts/command-executor.md](contracts/command-executor.md)
- Contrato notificacoes: [contracts/notification-events.md](contracts/notification-events.md)
- Quickstart: [quickstart.md](quickstart.md)

## Project Structure

### Documentation (this feature)

```text
docs/specs/tpanel-mvp/
|-- spec.md
|-- plan.md
|-- research.md
|-- data-model.md
|-- quickstart.md
`-- contracts/
    |-- command-executor.md
    |-- notification-events.md
    `-- ui-contracts.md
```

### Source Code (repository root)

Estrutura alvo para implementacao futura. Estes paths ainda serao criados nas etapas de tasks/implementacao.

```text
config/
|-- app.php.model
|-- database.php.model
|-- commands.php.model
|-- noticli.php.model
composer.json
public/
|-- index.php
|-- assets/
|   |-- css/
|   |-- js/
|   `-- images/
src/
|-- Controllers/
|-- Services/
|-- Command/
|-- Monitoring/
|-- Repositories/
|-- Security/
|-- Support/
templates/
|-- layouts/
|-- dashboard/
|-- monitoring/
|-- services/
|-- audit/
scripts/
|-- system/
|-- sudoers/
database/
|-- init/
|-- update/
tests/
|-- unit/
|-- integration/
vendor/              # generated locally by Composer; ignored by Git
docs/
|-- adr/
|-- briefing/
|-- specs/
`-- constitution.md
```

**Structure Decision**: A implementacao deve usar Composer sem framework para o bootstrap do MVP, conforme [ADR-001](../../adr/ADR-001-composer-without-framework.md). Composer deve fornecer autoload PSR-4, metadados do projeto e scripts de validacao/teste quando definidos. O codigo deve permanecer framework-independent; se um microframework for adotado depois, a decisao deve ser registrada em novo ADR ou emenda.

## Convenções de Borda

| Camada | Case style | Validacao | Fonte da verdade |
|--------|------------|-----------|------------------|
| MySQL tables/columns | camelCase | constraints, indexes, migrations | future `database/init/*.sql` and `database/update/*.sql` |
| MySQL constraints | snake_case | schema review | future migrations |
| PHP domain fields | camelCase | service validators | `src/Services/` and `src/Security/` validators |
| UI payload fields | camelCase | controller/service contract tests | `contracts/ui-contracts.md` |
| UI element keys | kebab-case | view rendering tests/manual viewport review | templates and CSS conventions |
| Form fields | camelCase | controller validation | `contracts/ui-contracts.md` |
| Command catalog keys | kebab-case or dot-separated stable keys | catalog validation | `contracts/command-executor.md` and `config/commands.php.model` |
| Notification categories | lowercase words | allowlist validation | `contracts/notification-events.md` |

**Mapper layer (DB <-> DTO)**: Repositories convert database rows to PHP domain arrays/objects using the same camelCase names where possible. Any display-specific transformation belongs in services or view models, not SQL scripts.

**Validacao de schema**: Requests from UI forms are validated at controller boundary and again at service/command boundary for security-sensitive operations. Command parameters must validate against the command catalog before any process invocation.

## Data and Persistence Plan

MySQL persistence is required for:

- users and roles mapped from Apache identity;
- audit records for administrative, denied and monitor event actions;
- alerts, acknowledgements and comments;
- metric readings and health snapshots;
- authorized action catalog metadata when not purely static config;
- notification event result tracking.

Metric retention:

- default retention: 90 days;
- retention must be configurable;
- each metric reading has `collectedAt`, `createdAt` and optional `expiresAt` data to support purge;
- MVP stores raw metric readings with retention only; persistent rollups/aggregations are deferred until there is enough operational data to justify their shape;
- historical collection and UI refresh behavior are explicit and configurable.

Initial collection frequency:

| Metric category | Initial interval | Fresh when | Stale when | Notes |
|-----------------|------------------|------------|------------|-------|
| SYSTEM | 60 seconds | age <= 120 seconds | age > 120 seconds | Host, OS, kernel, uptime and load summary. |
| CPU | 30 seconds | age <= 90 seconds | age > 90 seconds | Total, per-core when available, frequency and hot processes. |
| MEMORY | 30 seconds | age <= 90 seconds | age > 90 seconds | RAM, swap, cache and buffers. |
| STORAGE | 300 seconds | age <= 900 seconds | age > 900 seconds | Filesystems, free space, I/O and inodes. |
| DISK_HEALTH | 900 seconds | age <= 3600 seconds | age > 3600 seconds | SMART and disk temperature are slower and may be unavailable. |
| RAID | 300 seconds | age <= 900 seconds | age > 900 seconds | Includes sync/degraded state when RAID exists. |
| NETWORK | 30 seconds | age <= 90 seconds | age > 90 seconds | Interfaces, traffic counters, errors and latency probes. |
| SERVICE | 60 seconds | age <= 180 seconds | age > 180 seconds | systemd, Docker and container state. |
| PROCESS | 30 seconds | age <= 90 seconds | age > 90 seconds | Top CPU/RAM process snapshots. |
| LOG | 300 seconds | age <= 900 seconds | age > 900 seconds | Recent journal/syslog/error summaries, not full log archival. |
| SECURITY | 300 seconds | age <= 900 seconds | age > 900 seconds | SSH failures, firewall and update indicators. |
| SENSOR | 60 seconds | age <= 180 seconds | age > 180 seconds | Temperatures, fans and power when available. |
| SCHEDULE | 300 seconds | age <= 900 seconds | age > 900 seconds | Cron and systemd timer visibility. |

Freshness policy:

- `FRESH`: the latest successful reading exists and its age is within the category threshold.
- `STALE`: the latest successful reading exists but is older than the category threshold.
- `UNKNOWN`: no successful reading exists yet, the collector has not run, or the source is unavailable/permission-denied without a previous usable reading.
- `UNAVAILABLE` remains a severity for a metric/source; it can coexist with `FRESH` when a recent collector run confirmed that the capability is not installed or not present.

Historical storage policy:

- raw readings are retained for the configured retention window, initially 90 days;
- `expiresAt` is calculated as `collectedAt + retentionDays` for historical readings;
- dashboard/current-state reads use the latest reading per `metricCategory`, `metricName` and `source`;
- charts initially query raw readings over bounded windows and may downsample in memory for display only;
- no aggregation table is introduced in the MVP unless later performance testing proves raw reads cannot satisfy the dashboard and detail views.

Refresh behavior:

- UI auto-refresh is configurable, enabled by default for monitoring views and starts at 30 seconds;
- UI refresh reads existing collected data and must not directly execute expensive collectors on every browser refresh;
- collector scheduling is planned as local CLI/cron/systemd timer work in the implementation phase, not as a REST polling API.

Backup/restore expectations:

- audit records, users, roles, authorized actions, alerts, acknowledgements, comments, configuration references and notification records require at least daily database backup;
- production backup retention should keep 30 daily backups and 12 monthly backups for audit-oriented data unless the operator documents a stricter local policy;
- runtime `config/*.php` files are backed up separately through a protected local/secret mechanism and are never committed;
- metrics and health snapshots are backed up daily but only need to be retained within `monitoring.retentionDays`, initially 90 days;
- restore validation must run weekly or before production use against a temporary schema with non-sensitive sample data;
- the operational procedure is documented in [../../operations/backup-restore.md](../../operations/backup-restore.md).

## Security Plan

- Apache remains the authentication boundary.
- TPanel maps authenticated identities to roles.
- Administrador is required for administrative actions.
- Monitor may acknowledge alerts and comment on events only.
- Command execution requires catalog entry, role authorization, parameter validation, confirmation when required and timeout.
- Denied, failed, timed-out and successful attempts all create audit records.
- Logs, audit records, UI messages and notifications must sanitize secrets.
- Runtime config files containing credentials remain ignored by Git; committed examples use `.model`.

### NotiCLI Configuration Boundary

TPanel stores only invocation policy for NotiCLI in `config/noticli.php`: enabled flag, binary path, `send` command metadata, allowed categories/priorities, timeout, default sender and optional config override path. Provider secrets, delivery accounts, routes, Slack webhooks, SMTP credentials and Telegram tokens remain exclusively in the NotiCLI configuration managed outside TPanel.

By default, `useConfigOverride=false` and `configPath=null`, allowing NotiCLI to resolve `config/noticli.json` beside its binary. If integration is disabled or the binary/config is unavailable, notification events are recorded as `SKIPPED` rather than exposing diagnostics containing secrets.

### Log Access Policy

Monitor access to logs is limited to sanitized summaries that do not expose raw authentication logs, audit trails or command output. Administrador may access all known log sources.

| Log source | Monitor | Administrador | Notes |
|------------|---------|---------------|-------|
| `journal.recent-errors` | yes | yes | Sanitized warning/error summary only |
| `syslog.recent-errors` | yes | yes | Sanitized warning/error summary only |
| `security.summary` | yes | yes | Sanitized aggregate SSH/firewall/update indicators |
| `auth.raw` | no | yes | Raw auth logs are restricted |
| `audit.records` | no | yes | Audit trail may expose administrative context |
| `command.output` | no | yes | Command stdout/stderr summaries remain admin-only |

### Severity Threshold Policy

Initial severity thresholds are centrally configurable through `config/monitoring.php` using `config/monitoring.php.model` as the versioned template. Missing optional capabilities are classified as `UNAVAILABLE`; available sources with values over warning/critical thresholds are classified as `WARNING` or `CRITICAL`; otherwise they remain `NORMAL`.

| Area | WARNING | CRITICAL | UNAVAILABLE rule |
|------|---------|----------|------------------|
| CPU | total usage >= 75% | total usage >= 90% | total usage cannot be read |
| Memory | RAM used >= 80% | RAM used >= 90% | RAM totals cannot be read |
| Storage | filesystem or inode use >= 80% | filesystem or inode use >= 90% | no filesystem/I/O source readable |
| SMART | temperature >= 55C or reallocated sectors >= 1 | temperature >= 65C, reallocated sectors >= 50, critical errors >= 1 or SMART health not `PASSED` | no disk devices/tooling readable |
| RAID | array syncing | degraded array or degraded disks > 0 | no RAID arrays detected/readable |
| Network | latency >= 100ms or interface errors >= 1 | latency >= 250ms or interface errors >= 100 | no interface/gateway/DNS/latency source readable |
| Services | inactive/activating/deactivating service, exited container | failed service | systemd/Docker source command unavailable |

## UI Plan

- The first screen is the operational dashboard.
- Desktop uses persistent/collapsible sidebar and top bar.
- Mobile uses hamburger navigation and single-column cards.
- Cards and tables must have stable dimensions and clear severity states.
- Light/dark theme support is part of MVP.
- Missing optional capabilities are shown as unavailable/not installed, not as fatal errors.

## Validation Plan

Primary validation is defined in [quickstart.md](quickstart.md). Implementation tasks should add automated coverage where feasible for:

- role authorization;
- command catalog validation;
- parameter validation;
- idempotency/double-submit prevention;
- audit record creation;
- secret redaction;
- dashboard payload shape;
- MySQL persistence and retention behavior;
- responsive layout smoke checks.

## Post-Design Constitution Re-check

| Principio | Status | Notas |
|-----------|--------|-------|
| Security Before Capability | PASS | Design keeps deny-by-default command execution and no arbitrary commands. |
| Modular Monolith With Clear Layers | PASS | Project structure enforces internal layers while staying monolithic. |
| Auditable Operations And Observable Failures | PASS | Data model and contracts include audit and result records. |
| Local, Portable, And Repository-Safe Configuration | PASS | Uses local stack and `.model` config pattern. |
| Premium, Responsive, And Efficient Interface | PASS | UI and quickstart include responsiveness and visual-state checks. |

## Complexity Tracking

No constitution violations. No complexity exceptions required.
