# BlueCore Roadmap

This roadmap records reviewable direction, not release dates or automatic commitments. Operator or maintainer approval remains required for licensing, release, dependency, and major architecture decisions.

## Current alpha baseline

BlueCore currently provides:

- PHP 8.2+ framework and Composer plugin packaging;
- application, gateway, registration, add-on, datasource, installation, response, template, and error lifecycle hooks;
- structured installation, registration, population, and migration-run outcomes;
- SQL and SQLite model projections;
- declarative integration mapping;
- optional Presence authentication bridge discovery;
- provider-injected generation interfaces and provider-free examples;
- clean Composer project installer parity coverage.

## Active, approved work

### Documentation foundation â€” issue #101

- Reconcile README claims and repository links.
- Establish comprehensive specification, architecture, roadmap, testing, contribution, collaboration, and license surfaces.
- Preserve explicit library/framework boundaries and non-goals.
- Record the installed-package documentation experience.

### Migration revert reliability â€” issue #99

- Return structured, backward-compatible revert outcomes.
- Preserve history for failed and unattempted reverts.
- Represent no-op, unavailable, failed, partial, and completed states explicitly.
- Add provider-free success/failure/mixed-result fixtures.

Issue #99 remains a separate behavioral change and should follow its own branch, tests, review, and landing decision.

## Candidate follow-up work

These items require an owning issue and priority approval before implementation:

- certify DevElation upgrades against installed-revision template present, empty, missing/unreadable, fallback, and handle-cleanup fixtures;
- define alpha exit criteria and a supported compatibility window;
- decide whether a small installed documentation set should ship in Composer archives;
- review older framework areas for alignment with structured outcomes, strict typing, and bounded lifecycle evidence;
- reduce local SQLite duplication when released DevElation storage contracts provide equivalent model-facing behavior;
- define optional generation adapters only when a provider-neutral contract and reusable fixtures exist.

## Dependencies and gates

- DevElation changes require a released version plus BlueCore consumer evidence before constraint updates.
- Optional sibling integrations require owner-confirmed contracts and representative BlueCore fixtures before compatibility claims.
- Public API, license, dependency, release, tag, and publication changes require maintainer review.
- Protected branches remain pull-request only; successful CI does not authorize landing.

## Explicit non-goals

- Product-specific workflows, tenant/user onboarding, deployment orchestration, or UI ownership.
- Provider transport, prompt/model policy, billing, or a bundled AI runtime.
- Host command authority, evidence provenance, or domain-specific integration-language parsing.
- Absorbing generic DevElation storage, behavior, or primitive responsibilities.
