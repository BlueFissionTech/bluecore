# PHP runtime patch qualification plan

Status: **unknown / not qualified**. This is an evidence-only plan for
[issue #108](https://github.com/BlueFissionTech/bluecore/issues/108), not a runtime
upgrade, security attestation, release approval, or change to the PHP `>=8.2`
support floor. Evidence was collected on 2026-10-22.

## Source and target

The [PHP security advisory of 2026-10-21](https://www.php.net/archive/2026.php#2026-10-21-5)
identifies fixed releases 8.2.35, 8.3.36, 8.4.27, and 8.5.12. Application changes
are not a substitute for the runtime fix. PHP 8.2 security support ends on
2026-12-31; preserving a syntax floor is not a promise to operate unsupported
runtimes. Support-policy changes require separate maintainer review.

Prefer a fixed release in the host's existing minor line, or a vendor backport
whose advisory and exact package/build identity establish coverage. Do not
infer a backport from a distribution version string. No production inventory,
vendor attestation, fixed runtime, or running image digest was available in this
audit. Coordinate operational qualification separately through the
[shared runtime plan](https://github.com/BlueFissionTech/orchestrate/issues/182).

| Surface | Observed evidence | Candidate | Classification / missing proof |
| --- | --- | --- | --- |
| Existing development CLI | PHP 8.2.11, CLI SAPI | 8.2.35, same minor | Unknown; fixed executable/build and repeatable test environment absent |
| CI | Floating PHP 8.2 / Composer 2.2 and PHP 8.3 / latest Composer | 8.2.35 / 8.3.36 | Unknown; exact runtime/build, extension and lock evidence needed per run |
| PHP 8.4 | Not in the current CI matrix | 8.4.27 | Unknown; isolated runner, deprecation and compatibility evidence needed |
| PHP 8.5 | Not in the current CI matrix | 8.5.12 | Unknown; isolated runner and compatibility evidence needed |
| Library compose build | `docker/php/Dockerfile`, floating `php:8.2-fpm` | Fixed 8.2 FPM package/image | Unknown; resolved digest, SAPI/config/extensions and host smoke tests needed |
| Legacy nested Dockerfile | `docker/php/php/Dockerfile`, floating `php:8.2-fpm` | Owner-selected fixed image if still used | Unknown; not selected by `docker/compose.lib.yml`; usage unverified |
| Deployed web runtimes | No verified inventory | Owner-selected same-minor fixed build | Unknown; host owner must identify inventory and exposure; not N/A |

These target versions are advisory candidates, not versions executed here.
Neither version constraints nor green tests on an unidentified floating image
establish that a security-fixed build was used. BlueCore is a framework package,
not the owner of deployed host configuration or production rollout.

## Reproducible source and dependency boundary

- Audited source: `cf6785ff9de0cc7525d438bf89247710149d8a37`.
- Audited `composer.lock` Git blob: `1e898a60310e8db2636bb6a8a289b38119667c18`;
  Composer content hash: `ac2f5ed79df53ac71cc173440b1abb2b`.
- Manifest: PHP `>=8.2`, DevElation `^1.3.44`, Composer plugin API `^2.0`.
- Lock: DevElation v1.3.47 at
  `4b9079b8b34010ddd5e0f92774ae9abafbe9ea77`, PHPUnit 11.5.57,
  PHPSpreadsheet 5.10.0. These are observed inputs, not a new upgrade proposal.
- No `config.platform` emulation or lock platform override was present;
  `platform-check` is enabled. Do not use `--ignore-platform-reqs` to manufacture
  compatibility. The lock belongs to framework development; consuming projects
  must qualify their own resolved locks, plugins, and optional integrations.
- The package is a Composer plugin. Installation and plugin/script execution
  are a separate trust boundary; approval of this document does not approve
  installing dependencies or running third-party install hooks.

The observed CLI loaded Core, bcmath, calendar, ctype, date, filter, hash, iconv,
json, SPL, pcre, random, readline, Reflection, session, standard, mysqlnd,
tokenizer, zlib, libxml, dom, PDO, openssl, SimpleXML, xml, xmlreader, xmlwriter,
curl, fileinfo, gd, intl, mbstring, exif, mysqli, Phar, pdo_mysql, pdo_sqlite,
parallel, ds, soap, sqlite3, and zip. This is not an FPM inventory. The selected
Dockerfile explicitly builds zip, mysqli, pdo, pdo_mysql, and pdo_pgsql; Xdebug is
optional. Base-image extensions and distribution libraries need separate capture.

Lock platform checks also cover PHP 64-bit, Composer plugin API, ctype, dom,
fileinfo, filter, gd, iconv, json, libxml, mbstring, pcre, phar, simplexml,
tokenizer, xml, xmlreader, xmlwriter, zip, and zlib. Composer may satisfy some
requirements via polyfills; record native extension availability independently.
SQLite model tests additionally need PDO SQLite. Optional live service tests
must stay opt-in; do not enable a service or network test merely to fill a row.

## Executed checks and limits

On the audited source with the existing PHP 8.2.11 CLI:

| Command | Actual result |
| --- | --- |
| `composer validate --no-check-publish --strict` | Pass: manifest valid |
| `composer check-platform-reqs --lock` | Pass: all reported lock platform requirements satisfied |
| `php scripts/lint.php` | Pass: source, test and example syntax checks |
| `php vendor/phpunit/phpunit/phpunit --do-not-cache-result` | Did not execute tests: runner reported it could not open the configured `vendor/autoload.php` bootstrap |
| `php vendor/phpunit/phpunit/phpunit --bootstrap ./vendor/autoload.php --do-not-cache-result tests` | Same bootstrap-opening failure; explicit path did not repair it |

An independent CLI `is_file` / `realpath` probe resolved the existing autoloader.
The runner failure therefore does **not** prove a missing dependency file or a
framework compatibility failure. Preserve that diagnostic and resolve the test
environment before claiming a baseline. No dependency repair/install was run.
No tests have qualified the fixed versions listed above. Old review-branch CI
and different source/lock combinations must not be substituted for this matrix.
The existing main CI [run 39500444252](https://github.com/BlueFissionTech/bluecore/actions/runs/39500444252)
completed successfully on 2026-10-20 for the dependency-update push. Its floating
runtime configuration does not qualify one of the fixed builds in this plan.

## Finite qualification sequence

1. **Maintainer / runtime owner:** choose one exact fixed same-minor build for
   an isolated environment. Record package/version/vendor advisory or image
   digest, OS/architecture, CLI/FPM SAPI, extension versions and sanitized config
   differences. Obtain approval for provisioning, dependencies and any CI cost.
   Do not alter a live service, secret, provider, database, or traffic route.
2. **BlueCore maintainer:** reproduce the bootstrap issue on an unchanged copy
   of the audited source/lock. Capture the first failure, executable identity and
   configuration used by the runner. Repair only the demonstrated environment
   defect through a separately reviewed change; do not regenerate dependencies
   or suppress bootstrap/test failures without approval.
3. **BlueCore maintainer:** on each approved isolated fixed runtime, run the
   following commands from the checkout root using that runtime's `php` and
   Composer. Record exit codes, test/assertion counts, skips, diagnostics and
   exact source/lock/build identity. Provisioning is a prerequisite, not an
   action authorized by the command list.

   ```sh
   php --version
   php --modules
   composer --version
   composer validate --no-check-publish --strict
   composer check-platform-reqs --lock
   php scripts/lint.php
   php vendor/phpunit/phpunit/phpunit --do-not-cache-result tests
   php tests/Integration/project-installer-parity.php
   ```

4. **BlueCore maintainer / independent reviewer:** inspect focused model,
   gateway, engine binding, callback/hook, response/template and error-handler
   results. Include shutdown classification and serialization/round-trip
   contracts where covered; do not claim a comprehensive session or serializer
   audit from unrelated passing tests. Explicitly characterize the known PHP
   8.4 implicit-nullability candidates in generator signatures before proposing
   any signature repair. Preserve omitted/null/non-null inputs and exceptions.
   No behavioral repair is selected until a failing-before case demonstrates it.
5. **Host owners:** test their own exact locks and HTTP entrypoints under the
   fixed web SAPI, including routing context isolation, add-on boot failure,
   generic pre-effect denial, session persistence failures and shutdown/error
   handling. BlueCore PR #117 and upstream DevElation issue #329 concern denial
   and persistence truthfulness; neither replaces a PHP fix or proves session
   durability. No exploit payloads, resource-exhaustion or production probes.
6. **Maintainer:** publish a result per exact combination: compatible-tested,
   incompatible (with reproducible failure), unknown (precise missing evidence),
   or N/A (verified unused surface with owner evidence). Reviewer acceptance,
   release approval, host operational approval and exact-head PR landing
   confirmation remain distinct. Do not close #108 on the strength of this plan.

## Recovery and approval boundaries

This documentation-only change needs no runtime rollback. Qualification should
use disposable isolated environments and retain the original source, lock and
runtime identities for comparison. Remove only test-owned resources after
ownership verification. If an approved host rollout later fails, its verified
owner must choose recovery: pause promotion, restore a known security-fixed
artifact/configuration, or isolate service under an approved incident plan.
Returning an exposed service to a known vulnerable PHP build is not an automatic
rollback. No database rollback, destructive cleanup, provider change, credential
change, restart or traffic switch is authorized by this document.

The current next decision is approval of an isolated fixed-runtime test target
and its provisioning owner, not approval to deploy or change the support floor.
Known consumer observations must be tracked as owner attestations with their own
source/lock/build and results; an incomplete consumer inventory stays an explicit
coverage gap rather than an assertion of ecosystem-wide compatibility.
