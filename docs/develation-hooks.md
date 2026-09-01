# DevElation Hooks

BlueCore exposes stable DevElation hooks at framework lifecycle boundaries so applications and add-ons can extend behavior without replacing framework internals. Hooks are inactive until `DevElation::up()` is called.

## Contract

Hook names use `bluecore.<area>.<operation>.<phase>`.

- Actions are observational. They receive the documented arguments and do not replace framework values.
- Filters transform one documented value. Each callback must return a value compatible with the filter contract.
- Lower numeric priorities run before higher priorities, following DevElation ordering.
- Re-entry is suppressed per Engine and hook name by default. `Engine::hookRecursionDepth()` provides an explicit opt-in bounded by `Engine::MAX_HOOK_RECURSION_DEPTH`.
- Security checks remain authoritative. Hooks must not be used to bypass validation, authorization, or gateway denial.

## Engine actions

Owner: `Engine`. All payloads are observational references: callbacks may inspect them but must not mutate protected Engine state or replace control flow. Engine payloads contain framework state, so callbacks must avoid logging configuration values, request credentials, or session contents.

| Constant and hook | Phase | Payload | Failure semantics |
| --- | --- | --- | --- |
| `Engine::HOOK_BOOTSTRAP_BEFORE`<br>`bluecore.engine.bootstrap.before` | Before bootstrap | `Engine $engine` | Runs before bootstrap work. Callback exceptions stop bootstrap. |
| `Engine::HOOK_BOOTSTRAP_AFTER`<br>`bluecore.engine.bootstrap.after` | After bootstrap | `Engine $engine` | Runs only after successful bootstrap. |
| `Engine::HOOK_BOOTSTRAP_FAILED`<br>`bluecore.engine.bootstrap.failed` | Bootstrap failure | `LifecycleFailure $failure` | Observes sanitized failure metadata before the original exception is rethrown. |
| `Engine::HOOK_PROCESS_BEFORE`<br>`bluecore.engine.process.before` | Before request processing | `Engine $engine` | Runs before gateway processing. Callback exceptions stop processing. |
| `Engine::HOOK_PROCESS_AFTER`<br>`bluecore.engine.process.after` | After request processing | `Engine $engine` | Runs after success or controlled gateway denial. |
| `Engine::HOOK_PROCESS_DENIED`<br>`bluecore.engine.process.denied` | Controlled denial | `GatewayDenied $denial, Engine $engine` | Observes a fail-closed denial; it cannot clear it. |
| `Engine::HOOK_PROCESS_FAILED`<br>`bluecore.engine.process.failed` | Processing failure | `LifecycleFailure $failure` | Observes sanitized failure metadata before the original exception is rethrown. |
| `Engine::HOOK_RUN_BEFORE`<br>`bluecore.engine.run.before` | Before execution | `Engine $engine` | Runs only when processing has not been denied. Callback exceptions stop execution. |
| `Engine::HOOK_RUN_AFTER`<br>`bluecore.engine.run.after` | After execution | `Engine $result, Engine $engine` | Runs only after successful execution. |
| `Engine::HOOK_RUN_DENIED`<br>`bluecore.engine.run.denied` | Execution skipped | `GatewayDenied $denial, Engine $engine` | Observes the denial that prevented execution. |
| `Engine::HOOK_RUN_FAILED`<br>`bluecore.engine.run.failed` | Execution failure | `LifecycleFailure $failure` | Observes sanitized failure metadata before the original exception is rethrown. |

The `after` action is dispatched only when the operation completes normally. A gateway denial is a handled process outcome, so `process.denied` is followed by `process.after`; `run.denied` is dispatched when execution is intentionally skipped.

`LifecycleFailure` exposes only the hook, operation, dispatcher name, exception class, and numeric exception code. It intentionally omits the exception message, trace, request, configuration, session, and mutable Engine instance. Failure hooks never recursively redispatch themselves at the default depth.

```php
$engine->hookRecursionDepth(Engine::HOOK_PROCESS_BEFORE, 2);
```

The configured depth is local to that Engine instance and hook. Values must be between 1 and 8; depth 1 is the non-recursive default. Failure hooks cannot opt into recursive dispatch.

## Gateway filter

Owner: `DynamicGateway`. `DynamicGateway::FILTER_ARGUMENTS` identifies `bluecore.gateway.dynamic.arguments`. It runs during gateway request processing and receives the mutable request argument value. It must return a native array or a DevElation `Arr`; invalid results fail processing with `UnexpectedValueException` before controller execution. Because DevElation passes each filter result directly to the next callback, callbacks in a shared filter chain should accept `array|Arr`. BlueCore normalizes the final result to a native array before the next gateway or controller receives it.

Filters may add or normalize ordinary request arguments, but they must not remove or override authorization, CSRF, path-containment, or other fail-closed evidence.

During the alpha compatibility period, the previous dynamic-gateway filter is applied first and the neutral BlueCore filter is applied second. New integrations should register only `DynamicGateway::FILTER_ARGUMENTS`.

## Registration plan hooks

Owner: `RegistrationPlan`.

| Constant and hook | Type | Payload or value | Contract |
| --- | --- | --- | --- |
| `RegistrationPlan::FILTER_ENTRY`<br>`bluecore.registration.plan.entry` | Filter | `RegistrationEntry` | May return a replacement entry for the same section. The name and definition may change; moving an entry between sections is rejected. |
| `RegistrationPlan::HOOK_ENTRY_ADDED`<br>`bluecore.registration.plan.entry.added` | Action | `RegistrationEntry $entry, RegistrationPlan $plan` | Runs after a validated entry is stored. |
| `RegistrationPlan::HOOK_ENTRY_FAILED`<br>`bluecore.registration.plan.entry.failed` | Action | `LifecycleFailure $failure` | Observes sanitized composition failure metadata before the original exception is rethrown. |

Registration filters must return `RegistrationEntry`. Invalid types, empty names, and attempts to change the section fail before the plan is mutated. Re-entry into the same plan hook is suppressed; nested registration calls still execute with their unfiltered entry so the plan remains usable without recursively invoking the same callback.

## Add-on registration hooks

Owner: `AddOnManager`.

| Constant and hook | Type | Payload or value | Contract |
| --- | --- | --- | --- |
| `AddOnManager::FILTER_REGISTRATION_PLAN`<br>`bluecore.addon.registration.plan` | Filter | `RegistrationPlan` | Transforms the shared plan before active registration factories run. Must return `RegistrationPlan`. |
| `AddOnManager::HOOK_REGISTRATION_BEFORE`<br>`bluecore.addon.registration.before` | Action | `AddOn $addOn, Application $application, RegistrationPlan $plan, AddOnManager $manager` | Runs immediately before one active factory. |
| `AddOnManager::HOOK_REGISTRATION_AFTER`<br>`bluecore.addon.registration.after` | Action | `AddOn $addOn, Application $application, RegistrationPlan $plan, AddOnManager $manager` | Runs after one factory completes successfully. |
| `AddOnManager::HOOK_REGISTRATION_FAILED`<br>`bluecore.addon.registration.failed` | Action | `LifecycleFailure $failure` | Observes sanitized factory failure metadata. The structured registration result retains the existing operational diagnostics. |
| `AddOnManager::FILTER_CONTRIBUTIONS`<br>`bluecore.addon.contributions` | Filter | `Arr` | Transforms the final passive contribution summary. Must return `Arr`. |

Registration is guarded per add-on runtime key. A callback that re-enters active add-on loading receives a `registration_in_progress` status for the in-flight add-on, and its factory is not executed twice. Contribution filters run only after path containment and passive-data validation have completed; filters cannot make unsafe files eligible for loading.

## Datasource lifecycle hooks

Owner: `DatasourceManager`. Planning filters receive DevElation `Arr` values and may only reorder or remove entries discovered by BlueCore. They cannot add migration or population files, change the batch, change the iteration, or change automatic-population intent.

| Constant and hook | Type | Payload or value | Contract |
| --- | --- | --- | --- |
| `DatasourceManager::FILTER_MIGRATION_PLAN`<br>`bluecore.datasource.migration.plan` | Filter | `Arr` with `operation`, `batch`, `iteration`, and `deltas` | Must return `Arr`. `deltas` may contain a unique reordered subset of discovered files. |
| `DatasourceManager::HOOK_MIGRATION_BEFORE`<br>`bluecore.datasource.migration.before` | Action | `Arr $plan, DatasourceManager $manager` | Runs after plan validation and before migration execution. |
| `DatasourceManager::HOOK_MIGRATION_AFTER`<br>`bluecore.datasource.migration.after` | Action | `Arr $result, DatasourceManager $manager` | Runs after successful execution, including a successful no-op. |
| `DatasourceManager::HOOK_MIGRATION_FAILED`<br>`bluecore.datasource.migration.failed` | Action | `LifecycleFailure $failure` | Observes sanitized planning or execution failure metadata. |
| `DatasourceManager::FILTER_POPULATION_PLAN`<br>`bluecore.datasource.population.plan` | Filter | `Arr` with `operation`, `auto`, and `generators` | Must return `Arr`. `generators` may contain a unique reordered subset of discovered files. |
| `DatasourceManager::HOOK_POPULATION_BEFORE`<br>`bluecore.datasource.population.before` | Action | `Arr $plan, DatasourceManager $manager` | Runs after plan validation and before population execution. |
| `DatasourceManager::HOOK_POPULATION_AFTER`<br>`bluecore.datasource.population.after` | Action | `Arr $result, DatasourceManager $manager` | Runs after successful execution, including a successful no-op. |
| `DatasourceManager::HOOK_POPULATION_FAILED`<br>`bluecore.datasource.population.failed` | Action | `LifecycleFailure $failure` | Observes sanitized planning or execution failure metadata. |

## Installation lifecycle hooks

Owner: `InstallationExecutor`. Installation actions expose summaries only. Host context, approval evidence, stage evidence, exception messages, and checkpoint storage handles are not published through the global action surface.

| Constant and hook | Phase | Payload | Contract |
| --- | --- | --- | --- |
| `InstallationExecutor::HOOK_PREPARE_AFTER`<br>`bluecore.installation.prepare.after` | Plan prepared | `Arr $summary, InstallationExecutor $executor` | Reports plan id, status, diagnostic count, and checkpoint count after persistence. |
| `InstallationExecutor::HOOK_RESUME_AFTER`<br>`bluecore.installation.resume.after` | Plan resumed | `Arr $summary, InstallationExecutor $executor` | Runs only when a checkpoint resolves to a plan. |
| `InstallationExecutor::HOOK_EXECUTE_BEFORE`<br>`bluecore.installation.execute.before` | Before execution | `Arr $summary, InstallationExecutor $executor` | Runs after approval and readiness checks succeed. |
| `InstallationExecutor::HOOK_STAGE_BEFORE`<br>`bluecore.installation.stage.before` | Before stage | `Arr $summary, InstallationExecutor $executor` | Reports only plan id, status, and stage name. |
| `InstallationExecutor::HOOK_STAGE_AFTER`<br>`bluecore.installation.stage.after` | Stage success | `Arr $summary, InstallationExecutor $executor` | Reports sanitized outcome flags without stage evidence. |
| `InstallationExecutor::HOOK_STAGE_FAILED`<br>`bluecore.installation.stage.failed` | Stage failure | `LifecycleFailure $failure` | Observes sanitized stage failure metadata. |
| `InstallationExecutor::HOOK_CHECKPOINTED`<br>`bluecore.installation.checkpoint.after` | Checkpoint recorded | `Arr $summary, InstallationExecutor $executor` | Runs after the in-memory checkpoint is recorded and before durable plan save. |
| `InstallationExecutor::HOOK_EXECUTE_AFTER`<br>`bluecore.installation.execute.after` | Execution success | `Arr $summary, InstallationExecutor $executor` | Runs after completion is persisted. |
| `InstallationExecutor::HOOK_EXECUTE_FAILED`<br>`bluecore.installation.execute.failed` | Execution failure | `LifecycleFailure $failure` | Observes sanitized readiness or stage failure metadata. |

## Response, template, and error hooks

Owner: `HelperLifecycleHooks`. These constants cover Composer-loaded global helpers that do not have a class owner. Re-entry into the same helper hook is suppressed.

| Constant and hook | Type | Payload or value | Contract |
| --- | --- | --- | --- |
| `HelperLifecycleHooks::FILTER_RESPONSE`<br>`bluecore.response.prepare` | Filter | `Arr` with `data` and integer `status` | Must return `Arr`. The status must remain between 100 and 599. |
| `HelperLifecycleHooks::HOOK_RESPONSE_BEFORE`<br>`bluecore.response.before` | Action | Sanitized `Arr` with `status` and `content_type` | Runs before the DevElation `Response` is filled and headers are prepared. Response data is not exposed. |
| `HelperLifecycleHooks::HOOK_RESPONSE_AFTER`<br>`bluecore.response.after` | Action | Sanitized `Arr` with `status` and `content_type` | Runs after response preparation and immediately before `Response::send()`. DevElation delivery exits on its complete event, so this is not a post-delivery callback. |
| `HelperLifecycleHooks::HOOK_RESPONSE_FAILED`<br>`bluecore.response.failed` | Action | `LifecycleFailure $failure` | Observes sanitized preparation or extension failure metadata before the original exception is rethrown. |
| `HelperLifecycleHooks::EVENT_RESPONSE_PREPARED`<br>`bluecore.response.prepared` | Event | Sanitized response summary `Arr` | A genuine prepared-response event triggered immediately before delivery. Register it with `listen()`, then attach callables with `subscribe()`. |
| `HelperLifecycleHooks::FILTER_TEMPLATE_DATA`<br>`bluecore.template.data` | Filter | Template data `Arr` | Must return `Arr`. Theme selection and the relative template file remain framework-owned and cannot be changed through this filter. |
| `HelperLifecycleHooks::HOOK_TEMPLATE_BEFORE`<br>`bluecore.template.before` | Action | Sanitized `Arr` with `theme`, `file`, and `data_count` | Runs after data filtering and before renderer selection. Data values are not exposed. |
| `HelperLifecycleHooks::FILTER_TEMPLATE_OUTPUT`<br>`bluecore.template.output` | Filter | Rendered `Str` | Must return `Str`. Runs only after a renderer returns a native string. |
| `HelperLifecycleHooks::HOOK_TEMPLATE_AFTER`<br>`bluecore.template.after` | Action | Sanitized template summary `Arr` | Runs after output filtering and adds `output_length` without exposing rendered content. |
| `HelperLifecycleHooks::HOOK_TEMPLATE_FAILED`<br>`bluecore.template.failed` | Action | `LifecycleFailure $failure` | Observes sanitized rendering or extension failure metadata before the original exception is rethrown. |
| `HelperLifecycleHooks::HOOK_ERROR_REPORTED`<br>`bluecore.error.reported` | Action | Sanitized error summary `Arr` | Runs after logging a handled PHP error. Message, file, line, and trace are omitted. |
| `HelperLifecycleHooks::HOOK_EXCEPTION_REPORTED`<br>`bluecore.exception.reported` | Action | Sanitized exception summary `Arr` | Adds only exception class and numeric code to the shared error summary fields. |
| `HelperLifecycleHooks::HOOK_FATAL_REPORTED`<br>`bluecore.fatal.reported` | Action | Sanitized fatal summary `Arr` | Runs after fatal-error logging. Hook failures are contained so they cannot replace error reporting. |

Response and template filter failures remain fail-closed. Error-report actions are observational and cannot replace the original logging, status, debug-output, or exit behavior.

```php
use BlueFission\Arr;
use BlueFission\BlueCore\Business\Managers\AddOnManager;
use BlueFission\BlueCore\Engine;
use BlueFission\BlueCore\Gateway\DynamicGateway;
use BlueFission\BlueCore\Hooks\HelperLifecycleHooks;
use BlueFission\BlueCore\Registration\RegistrationEntry;
use BlueFission\BlueCore\Registration\RegistrationPlan;
use BlueFission\DevElation;

DevElation::up();

DevElation::filter(
    DynamicGateway::FILTER_ARGUMENTS,
    function (array $arguments): Arr {
        $filtered = Arr::make($arguments);
        $filtered->set('trace_enabled', true);

        return $filtered;
    }
);

DevElation::action(
    Engine::HOOK_RUN_AFTER,
    function (Engine $result, Engine $engine): void {
        // Observe completed execution without replacing framework control flow.
    }
);

DevElation::filter(
    RegistrationPlan::FILTER_ENTRY,
    function (RegistrationEntry $entry): RegistrationEntry {
        return $entry;
    }
);

DevElation::filter(
    AddOnManager::FILTER_CONTRIBUTIONS,
    function (Arr $summary): Arr {
        return $summary;
    }
);

DevElation::listen(HelperLifecycleHooks::EVENT_RESPONSE_PREPARED);
DevElation::subscribe(
    function (Arr $summary): void {
        // React to a prepared response without inspecting its body.
    },
    HelperLifecycleHooks::EVENT_RESPONSE_PREPARED
);
```

## Integration hooks

The optional authentication bridge also exposes stable input/output filters and before/after actions through its public hook constants. Their value contracts are documented by the bridge types and tests.
