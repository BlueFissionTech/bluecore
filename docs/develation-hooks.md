# DevElation Hooks

BlueCore exposes stable DevElation hooks at framework lifecycle boundaries so applications and add-ons can extend behavior without replacing framework internals. Hooks are inactive until `DevElation::up()` is called.

## Contract

Hook names use `bluecore.<area>.<operation>.<phase>`.

- Actions are observational. They receive the documented arguments and do not replace framework values.
- Filters transform one documented value. Each callback must return a value compatible with the filter contract.
- Lower numeric priorities run before higher priorities, following DevElation ordering.
- Hook callbacks should avoid re-entering the lifecycle operation that dispatched them.
- Security checks remain authoritative. Hooks must not be used to bypass validation, authorization, or gateway denial.

## Engine actions

Owner: `Engine`. All payloads are observational references: callbacks may inspect them but must not mutate protected Engine state or replace control flow. Engine payloads contain framework state, so callbacks must avoid logging configuration values, request credentials, or session contents.

| Constant and hook | Phase | Payload | Failure semantics |
| --- | --- | --- | --- |
| `Engine::HOOK_BOOTSTRAP_BEFORE`<br>`bluecore.engine.bootstrap.before` | Before bootstrap | `Engine $engine` | Runs before bootstrap work. Callback exceptions stop bootstrap. |
| `Engine::HOOK_BOOTSTRAP_AFTER`<br>`bluecore.engine.bootstrap.after` | After bootstrap | `Engine $engine` | Runs only after successful bootstrap. |
| `Engine::HOOK_BOOTSTRAP_FAILED`<br>`bluecore.engine.bootstrap.failed` | Bootstrap failure | `Throwable $exception, Engine $engine` | Observes an exception before it is rethrown. |
| `Engine::HOOK_PROCESS_BEFORE`<br>`bluecore.engine.process.before` | Before request processing | `Engine $engine` | Runs before gateway processing. Callback exceptions stop processing. |
| `Engine::HOOK_PROCESS_AFTER`<br>`bluecore.engine.process.after` | After request processing | `Engine $engine` | Runs after success or controlled gateway denial. |
| `Engine::HOOK_PROCESS_DENIED`<br>`bluecore.engine.process.denied` | Controlled denial | `GatewayDenied $denial, Engine $engine` | Observes a fail-closed denial; it cannot clear it. |
| `Engine::HOOK_PROCESS_FAILED`<br>`bluecore.engine.process.failed` | Processing failure | `Throwable $exception, Engine $engine` | Observes an unexpected exception before it is rethrown. |
| `Engine::HOOK_RUN_BEFORE`<br>`bluecore.engine.run.before` | Before execution | `Engine $engine` | Runs only when processing has not been denied. Callback exceptions stop execution. |
| `Engine::HOOK_RUN_AFTER`<br>`bluecore.engine.run.after` | After execution | `Engine $result, Engine $engine` | Runs only after successful execution. |
| `Engine::HOOK_RUN_DENIED`<br>`bluecore.engine.run.denied` | Execution skipped | `GatewayDenied $denial, Engine $engine` | Observes the denial that prevented execution. |
| `Engine::HOOK_RUN_FAILED`<br>`bluecore.engine.run.failed` | Execution failure | `Throwable $exception, Engine $engine` | Observes an unexpected exception before it is rethrown. |

The `after` action is dispatched only when the operation completes normally. A gateway denial is a handled process outcome, so `process.denied` is followed by `process.after`; `run.denied` is dispatched when execution is intentionally skipped.

## Gateway filter

Owner: `DynamicGateway`. `DynamicGateway::FILTER_ARGUMENTS` identifies `bluecore.gateway.dynamic.arguments`. It runs during gateway request processing and receives the mutable request argument value. It must return a native array or a DevElation `Arr`; invalid results fail processing with `UnexpectedValueException` before controller execution. Because DevElation passes each filter result directly to the next callback, callbacks in a shared filter chain should accept `array|Arr`. BlueCore normalizes the final result to a native array before the next gateway or controller receives it.

Filters may add or normalize ordinary request arguments, but they must not remove or override authorization, CSRF, path-containment, or other fail-closed evidence.

During the alpha compatibility period, the previous dynamic-gateway filter is applied first and the neutral BlueCore filter is applied second. New integrations should register only `DynamicGateway::FILTER_ARGUMENTS`.

```php
use BlueFission\Arr;
use BlueFission\BlueCore\Engine;
use BlueFission\BlueCore\Gateway\DynamicGateway;
use BlueFission\DevElation;

DevElation::up();

DevElation::filter(
    DynamicGateway::FILTER_ARGUMENTS,
    fn(array $arguments): Arr => Arr::make($arguments)->set('trace_enabled', true)
);

DevElation::action(
    Engine::HOOK_RUN_AFTER,
    function (Engine $result, Engine $engine): void {
        // Observe completed execution without replacing framework control flow.
    }
);
```

## Integration hooks

The optional authentication bridge also exposes stable input/output filters and before/after actions through its public hook constants. Their value contracts are documented by the bridge types and tests.
