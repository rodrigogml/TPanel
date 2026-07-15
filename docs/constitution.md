<!--
Sync Impact Report
- Version: none -> 1.0.0
- Principios modificados: criacao inicial dos principios de governanca do TPanel
- Secoes adicionadas: Core Principles, Architecture and Security Boundaries, Quality Gates, Governance
- Secoes removidas: nenhuma
- Artefatos que precisam atualizacao:
  - AGENTS.md: pendente; ainda nao existe no projeto
  - docs/specs/{feature-short-name}/plan.md: nao aplicavel; specs ainda nao existem
  - docs/specs/{feature-short-name}/tasks.md: nao aplicavel; tasks ainda nao existem
- TODOs pendentes:
  - Definir politica exata de retencao de metricas e agregacoes historicas
  - Definir granularidade de permissoes do papel Monitor
  - Definir mapa inicial de comandos autorizados e parametros permitidos
  - Definir categorias e prioridades padrao para eventos enviados ao NotiCLI
-->

# TPanel / Turin Panel Constitution

## Core Principles

### I. Security Before Capability

Administrative capability MUST be denied by default. Every administrative action exposed by TPanel MUST be mapped to a previously authorized command, with explicit parameter validation, timeout handling, audited execution, and no path for arbitrary command execution.

Authentication MUST be delegated to Apache, and PHP MUST consume the authenticated identity instead of implementing a parallel authentication source in this phase. Linux execution boundaries MUST use a dedicated `tpanel` user and group, with scripts and executables owned by `tpanel`, and Apache's `www-data` user allowed to run only approved commands through narrowly scoped sudo rules.

### II. Modular Monolith With Clear Layers

TPanel MUST remain a PHP monolith in this phase, but its code MUST be organized by explicit layers: UI, controllers, services, command executor, and system scripts. Business logic MUST NOT be embedded directly in templates, shell scripts MUST NOT own application policy, and controllers MUST coordinate services rather than perform low-level system work.

New monitoring or administration areas SHOULD be added as modules that follow the same boundaries, so future expansion does not require changing the base architecture.

### III. Auditable Operations And Observable Failures

Every administrative action MUST produce an audit record containing actor, requested action, validated parameters, execution result, exit status when available, timestamp, and failure reason when applicable. Logs MUST avoid secrets and sensitive payloads.

Operational failures MUST be visible to the administrator through clear diagnostics and persistent logs. Integrations such as NotiCLI MUST record invocation result and exit code in TPanel without duplicating NotiCLI secrets inside TPanel.

### IV. Local, Portable, And Repository-Safe Configuration

The project MUST run with local/open source resources by default: PHP, Apache, Debian 13, MySQL, Linux system tools, and optional local NotiCLI integration. Runtime secrets and machine-specific configuration MUST NOT be committed to Git.

Every sensitive or environment-specific config file MUST have a committed `.model` example and a corresponding ignored real file. Documentation MUST describe required config keys without exposing real credentials, tokens, host passwords, webhook URLs, or private operational data.

### V. Premium, Responsive, And Efficient Interface

The user interface MUST be responsive across desktop, tablet, and mobile, with clear support for light and dark themes. The initial experience MUST be the usable monitoring panel, not a marketing page.

Dashboard and monitoring views MUST favor fast scanning, stable layouts, readable status hierarchy, and touch-friendly controls on mobile. Visual polish is a product requirement, but it MUST NOT compromise security, auditability, or performance.

## Architecture and Security Boundaries

The MVP architecture MUST preserve these boundaries:

- UI renders views and client-side interactions only.
- Controllers validate request intent, enforce role checks, and delegate work to services.
- Services implement application policy, aggregation, persistence, and orchestration.
- The command executor is the only layer allowed to invoke system commands.
- System scripts expose narrow, documented operations and parse only validated parameters.
- MySQL persists audit records, applicable configuration, and metric/history data.

The Monitor role MUST be read-oriented until a later specification explicitly grants additional capabilities. The Administrator role MAY execute approved administrative actions, but only through mapped commands and audited workflows.

The NotiCLI integration MUST be treated as an external command integration. TPanel SHOULD call `noticli send` non-interactively with explicit sender, category, priority, title, and message fields, while NotiCLI remains the source of truth for notification destinations, routes, and provider secrets.

## Quality Gates

Before a feature is considered complete, it MUST satisfy these gates:

- Security: no arbitrary command execution, no committed secrets, no unchecked shell parameters, and no broad sudo permissions.
- Auditability: administrative actions have persistent audit records and useful failure details.
- Performance: dashboard and monitoring pages avoid unnecessary expensive polling and keep collection frequency explicit.
- Reliability: command timeouts, validation failures, missing dependencies, and non-zero exits are handled predictably.
- UX: desktop and mobile layouts remain usable, readable, and stable under realistic data.
- Documentation: new configuration, command mappings, scripts, and operational prerequisites are documented with `.model` files where applicable.
- Testing: command validation, permission-sensitive services, persistence behavior, and critical parsing logic have focused tests or documented manual validation until automated coverage exists.

## Governance

This constitution governs architecture, implementation, documentation, and review decisions for TPanel. Specifications, plans, and tasks MUST align with the principles above. When a later document conflicts with this constitution, the constitution takes precedence unless it is amended first.

Amendments MUST be made through an explicit update to `docs/constitution.md` with:

- a SemVer version change;
- an ISO date in `Last Amended`;
- an updated Sync Impact Report;
- a short rationale for affected principles or sections;
- a list of specs, plans, tasks, or project instructions that need synchronization.

Version changes follow these rules:

- MAJOR: removes a principle or changes a principle in an incompatible way.
- MINOR: adds a new principle or materially expands governance requirements.
- PATCH: clarifies wording, fixes mistakes, or improves precision without changing meaning.

Exceptions MUST be documented in the relevant spec or ADR with scope, reason, risk, and expiration or review condition. Permanent exceptions require amending this constitution.

**Version**: 1.0.0 | **Ratified**: 2026-07-14 | **Last Amended**: 2026-07-14
