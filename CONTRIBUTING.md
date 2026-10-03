# Contributing to BlueCore

BlueCore welcomes focused fixes and reusable framework improvements. Contributions must remain within BlueCore's package boundary and be small enough for human review.

## Setup

Requirements:

- PHP 8.2 or newer
- Composer 2.x
- Git

Install dependencies from a source checkout:

```bash
composer install
composer check-platform-reqs
```

Do not commit credentials or local service configuration. Optional service-backed tests should remain disabled unless their documented environment variables and extensions are available.

## Branches

Use an issue-linked branch:

- `feature/<issue>-<slug>`
- `issue/<issue>-<slug>`
- `hotfix/<issue>-<slug>`

Do not commit directly to protected branches. Open a pull request for review.

## Design expectations

- Keep public contracts general and package-owned.
- Use established DevElation primitives, services, behaviors, data, and connection surfaces when they own the capability.
- Do not add application-specific product policy, provider transport, host authority, deployment behavior, or sibling-owned responsibilities.
- Preserve backward compatibility unless the issue and review explicitly approve a migration path.
- Add hooks only at stable lifecycle boundaries with documented value shapes and tests.
- Keep optional integrations availability-safe and provider-free tests deterministic.

## Validation

Run the focused tests for the changed area, then the baseline suite:

```bash
composer lint
vendor/bin/phpunit --do-not-cache-result
composer test:installer
```

See [tests.md](tests.md) for optional integration requirements. Documentation changes should also verify relative links and every documented command or example they alter.

## Pull requests

Every pull request should include:

- intent summary;
- user stories and acceptance criteria;
- key files changed;
- exact test commands and results;
- QA checklist;
- approval conditions and unresolved decisions;
- compatibility, migration, and optional environment notes when relevant.

Do not include local machine paths, private coordination details, secrets, or unpublished downstream context in public artifacts.

## Security reports

Do not open a public issue containing credentials, private data, or actionable exploit details. Contact the maintainers privately with the affected version, minimal reproduction, impact, and suggested disclosure timing.
