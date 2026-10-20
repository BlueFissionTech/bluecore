# Authentication denial boundary

`AuthenticationGateway` requires an authenticated identity and an explicit
`true` result from `Auth::setSession()` before dispatch continues. False or
exceptional setup denies dispatch. Cleanup is best effort: its failure cannot
allow a controller to be prepared or run, nor replace an earlier exception.

Denial retains the existing login redirect and `GatewayDenied` status302.
The exception message is generic; the original provider/cleanup exception is
retained as its cause for trusted diagnostics, not for transport rendering.
Hosts must not expose exception chains or record contents in public errors.

Missing `APP_KEY` now raises `RuntimeException` with the existing configuration
message instead of an unresolved namespaced exception. No key generation,
configuration-file mutation or cryptographic behavior is changed.

This is a bounded part of issue #113, not complete authentication assurance.
It trusts the provider's reported session outcome; it does not prove durable
session storage, revocation, administrator roles, tenant membership or expiry.
In particular, the inspected DevElation authenticator's cookie helper returns
true after its storage write without verifying the storage outcome. That
upstream truthfulness boundary needs separate provider evidence and repair.
Constructor/dependency resolution before gateway execution remains a bootstrap
failure boundary. CSRF typed denial and pre-effect integration remain open.

Run the real Engine dispatch regressions and existing redirect contract with:

```sh
php vendor/bin/phpunit --do-not-cache-result tests/BlueCore/Gateway/AuthenticationFailureTest.php tests/BlueCore/Gateway/GatewayDenialTest.php tests/BlueCore/SecurityTest.php
```

Tests use authentication doubles but the real Engine processing/run sequence,
including controller construction counters, and do not open a live session or
modify credentials. Successful reported session establishment still dispatches.
