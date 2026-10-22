# Collaboration and Ownership

BlueCore is a reusable framework package. Cross-project requests are welcome when they reveal a general framework capability, compatibility gap, or quality improvement.

## BlueCore owns

- application lifecycle and framework extension contracts;
- registration, installation, add-on, datasource, gateway, model, template, and helper boundaries;
- Composer installers declared by this package;
- BlueCore-specific compatibility tests and migration guidance.

## Other owners retain

- DevElation: shared primitives, behaviors, services, connections, parsing/template internals, and generic storage;
- application hosts: routes, product workflow, tenancy, authorization decisions, deployment, and concrete service configuration;
- optional integration packages: their domain models, transport, storage, UI, and lifecycle contracts;
- provider adapters: network transport, credentials, model selection, usage policy, billing, and provider-specific errors.

## Requesting a change

A useful request includes:

- a provider-neutral or product-neutral user story;
- the current BlueCore behavior and smallest reproduction;
- reusable acceptance criteria;
- compatibility and failure expectations;
- evidence that the capability belongs in a base framework rather than an application adapter.

BlueCore may redirect a request upstream or to a sibling owner when that package owns the contract. Such routing is a scope decision, not a rejection of the underlying need.

## Review and authority

Issue agreement, technical review, CI success, release approval, and landing authorization are distinct. A review applies to the exact revision inspected. Maintainers retain license, release, dependency, and major architecture decisions.
