# Engine routing registration context

## Intent and acceptance

An Engine must own the routes registered during its bootstrap and explicit
registration callbacks. Separately constructed Engines must not share routes.
Legacy mapping calls outside an explicit scope retain the upstream default.

The native registration context belongs to DevElation (issue #334). BlueCore
integrates that context at its bootstrap boundary; it must not alias Application
singletons, reset private registries, or copy another application's maps.

Required conformance covers real Engine dispatch, no-match behavior, independent
instances loading the same mapping file, repeat bootstrap, nested registration,
exception restoration, gateway denial before controller effects, and preservation
of method, name and gateway metadata. Registration failures must propagate;
context restoration is not a transactional rollback of routes already registered.

The agreed native API is `Mapping::withApplication(Application $target, callable
$registration): mixed`. It returns the callback result and restores the prior
target on success or exception, with isolated Fiber contexts. An explicit final
Application argument on `Mapping::add()` or `crud()` takes precedence over the
scope; absent either, the legacy base Application default remains unchanged.
Engine bootstrap, including lifecycle hooks and configuration, executes within
the receiver's scope. Session lookup also uses that receiver, not the latest
globally selected Engine.

Deferred host callbacks that can also run outside bootstrap must capture their
own Engine and use `Mapping::withApplication($engine, $registration)` around the
registration callback. Dispatching an arbitrary application event is not an
implicit grant to change global registration ownership. Both nested successful
calls and thrown exceptions restore the previous registration target.

The integration also needs an explicit upstream repeat-load option: mapping
scripts contain registration effects, so process-wide `require_once` cannot
register the same script independently for two Engines. BlueCore records discovery
only after successful completion for the receiver; failed discovery remains
retryable. Helpers retain their existing one-time loading contract.
Earlier registration effects are not undone after a partial discovery failure;
mapping scripts must not perform external effects or rely on exactly-once retry.

## Qualification boundary

The installed DevElation v1.3.47 lacks scoped Mapping registration and repeatable
Loader execution. A source candidate must be qualified independently of the
installed dependency baseline. Neither a local source test nor this document
authorizes dependency adoption, release, publication, deployment or landing.

The optional immutable Presence bridge has a separate conformance gap (#120).
Routing success cannot clear bridge authentication, authority or result-contract
failures.

## Offline source qualification

Use an explicitly approved PHP executable and its matching extensions. Select
the native candidate checkout through `BLUECORE_DEVELATION_ROUTING_SOURCE` and
run:

```text
<php> <approved-runtime-flags> vendor/phpunit/phpunit/phpunit --bootstrap tests/Fixtures/develation-routing-source.php --do-not-cache-result tests/BlueCore/EngineRoutingContextTest.php
<php> <approved-runtime-flags> vendor/phpunit/phpunit/phpunit --bootstrap tests/Fixtures/develation-routing-source.php --do-not-cache-result
```

That opt-in bootstrap substitutes only the two actual native candidate classes,
Mapping and Loader. Composer platform checks and all other locked dependencies
remain active. Record the upstream commit and both file hashes; do not qualify a
moving checkout or interpret the override as dependency adoption.

If subprocess fixtures need the same approved modules, set
`BLUECORE_TEST_PHP_INI` to a temporary, non-secret test profile containing only
those matching runtime modules. Test launchers pass it as an explicit `-c`
argument even when the child deliberately receives an empty/custom environment.
No host environment inheritance, platform-check disabling or production Process
behavior changes are required. Unset the selection and remove the temporary
profile after the run. With no selection, test commands retain their prior
behavior; an absent selected profile is an error rather than a silent fallback.

## Recorded source-candidate result

Qualified against DevElation issue #334 source commit
`20a31b998b63bce42b37f75b26053662f307dc0c` with PHP 8.2.35 CLI and the
explicitly selected matching mbstring, fileinfo, gd, zip, sqlite3 and pdo_sqlite
modules:

- Routing conformance: 8 tests, 46 assertions passed. The same eight tests error
  against the original Engine source; the intermediate receiver-session fix
  exposes six misdirected-registration failures before native scoping is applied.
- Full source-pair suite: 179 tests, 737 assertions passed, including child
  fixtures launched with empty/custom environments.
- Syntax and strict offline Composer metadata validation passed.

This branch is not compatible with the currently locked v1.3.47 bootstrap:
`Mapping::withApplication()` and the repeatable Loader option are upstream
prerequisites, not installed APIs. Before ordinary installed-dependency CI or
release qualification, the native source needs review/publication and a released
pin needs explicit adoption approval. Do not weaken the conformance tests or
silently fall back to misdirected registration to make an older lock green.
