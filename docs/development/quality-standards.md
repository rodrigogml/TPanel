# Development Quality Standards

This document defines the initial quality conventions for TPanel implementation work.

## Local Validation Commands

Use Composer as the single local entrypoint for validation:

```bash
composer check
```

Individual commands:

```bash
composer validate --strict
composer lint
composer test
```

Expected coverage of each command:

| Command | Purpose |
|---------|---------|
| `composer validate --strict` | Validate Composer metadata and dependency definition |
| `composer lint` | Run PHP syntax checks for `public/`, `src/` and `tests/` |
| `composer test` | Run unit and integration tests with PHPUnit |
| `composer check` | Run the full local validation chain |

## PHP Conventions

- Use `declare(strict_types=1);` in every PHP file.
- Use namespace prefix `TPanel\` for application code.
- Use namespace prefix `TPanel\Tests\` for tests.
- Keep classes `final` by default unless extension is explicitly required.
- Prefer constructor injection for dependencies.
- Do not place business logic in templates or `public/index.php`.
- Do not execute system commands outside `TPanel\Command`.
- Do not read raw request/server values outside controllers or dedicated support/security code.

## Layer Responsibilities

| Layer | Directory | Responsibility |
|-------|-----------|----------------|
| UI entrypoint | `public/` | Web entrypoints and static assets only |
| Controllers | `src/Controllers/` | Request intent, role checks and service delegation |
| Services | `src/Services/` | Application policy and orchestration |
| Command | `src/Command/` | Authorized command execution boundary |
| Monitoring | `src/Monitoring/` | Metric and status collection adapters |
| Repositories | `src/Repositories/` | Persistence access and row mapping |
| Security | `src/Security/` | Identity, role and permission handling |
| Support | `src/Support/` | Small framework-independent application support |
| Templates | `templates/` | View markup only |
| System scripts | `scripts/system/` | Source versions of approved command wrappers |

## Test Conventions

- Unit tests live in `tests/unit/`.
- Integration tests live in `tests/integration/`.
- Every security-sensitive service must have a test for allowed and denied behavior.
- Every parser for system command output must have fixture-based tests.
- Every command execution path must test success, failure, timeout and invalid parameter handling.
- Integration tests must not require production secrets.

## Documentation and Config Safety

- Real runtime configuration files stay ignored by Git.
- Every real config file must have a `.model` counterpart.
- Do not commit credentials, tokens, webhook URLs, private hostnames or real operational payloads.
- Use ADRs for architectural decisions that affect bootstrap, dependencies, framework choice or security boundaries.

## Completion Gate

A task is not complete until:

- code or documentation is implemented;
- relevant tests or validations were executed;
- `docs/specs/tpanel-mvp/tasks.md` is updated;
- any skipped validation has a concrete reason in the final report.
