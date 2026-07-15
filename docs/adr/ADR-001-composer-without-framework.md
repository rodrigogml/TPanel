# ADR-001: Composer Without Framework for MVP Bootstrap

**Status**: Accepted
**Date**: 2026-07-14
**Decision Owner**: Alan Turing

## Context

The TPanel MVP must be a PHP monolith running on Apache, with clear internal layers for UI, controllers, services, command execution, monitoring, repositories, security and support code. The project also needs maintainable autoloading and testability without increasing architectural weight or violating the constitution's modular-monolith principle.

The open decision from the requirements checklist was whether the MVP should use PHP without Composer, Composer without a framework, or a lightweight microframework.

## Decision

Use Composer without a web framework for the MVP bootstrap.

The initial implementation should use Composer primarily for:

- PSR-4 autoloading;
- development/test dependencies when selected;
- consistent project metadata and scripts.

The MVP should not adopt a web framework or microframework unless a future ADR justifies it with concrete implementation pressure.

## Rationale

Composer without a framework gives the project a clean module and test foundation while keeping the runtime simple for Apache/PHP deployment. It supports the planned internal layers without outsourcing architectural policy to a framework too early.

This choice preserves:

- PHP monolith architecture;
- explicit UI/controller/service/command boundaries;
- low dependency footprint;
- future optional migration to a microframework if justified.

## Alternatives Considered

### PHP without Composer

Rejected for the MVP because it would make autoloading, tests and dependency hygiene harder as soon as the codebase grows beyond a few files.

### Lightweight Microframework

Rejected for the initial MVP because routing convenience does not yet outweigh the added dependency and architectural decision surface. It remains a future option if plain controllers/routing become costly.

## Consequences

- Bootstrap tasks must create `composer.json` with PSR-4 autoloading.
- Source code should remain framework-independent.
- Routing and controller dispatch should be explicit and small at first.
- Tests and validation commands should be wired through Composer scripts once the test tooling is chosen.
- Any future framework adoption requires a new ADR or amendment to this decision.
