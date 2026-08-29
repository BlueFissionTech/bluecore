<?php

namespace BlueFission\BlueCore\Integration\Presence;

use BlueFission\Arr;
use BlueFission\Collections\Collection;
use BlueFission\DevElation as Dev;
use BlueFission\Flag;
use BlueFission\Func;
use BlueFission\Obj;
use BlueFission\Presence\Annex\AnnexAdapter;
use BlueFission\Presence\Auth\AuthResult;
use BlueFission\Presence\Auth\Principal;
use BlueFission\Presence\Auth\PrincipalInterface;
use BlueFission\Presence\Bridge\BridgeContext;
use BlueFission\Presence\Bridge\BridgeInterface;
use BlueFission\Presence\Bridge\BridgeResult;
use BlueFission\Presence\Session\Participant;
use BlueFission\Presence\Session\Session;
use BlueFission\Presence\Session\SessionInterface;
use BlueFission\Str;
use BlueFission\Val;
use Throwable;

class PresenceAuthenticationBridge implements BridgeInterface
{
    public const HOST = 'bluecore';
    public const NAME = 'bluecore.authentication';
    public const HOOK_INPUT = 'bluecore.presence.bridge.input';
    public const HOOK_BEFORE = 'bluecore.presence.bridge.before';
    public const HOOK_OUTPUT = 'bluecore.presence.bridge.output';
    public const HOOK_AFTER = 'bluecore.presence.bridge.after';

    private Func $principalMapper;
    private Func $sessionMapper;

    public function __construct(
        ?Func $principalMapper = null,
        ?Func $sessionMapper = null,
        private ?object $annexAdapter = null
    ) {
        $this->principalMapper = $principalMapper
            ?? new Func(fn (mixed $authenticator, BridgeContext $context): PrincipalInterface =>
                $this->mapPrincipal($authenticator, $context)
            );
        $this->sessionMapper = $sessionMapper
            ?? new Func(fn (mixed $session, PrincipalInterface $principal, BridgeContext $context): array =>
                $this->mapSession($session, $principal, $context)
            );
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function supports(BridgeContext $context): bool
    {
        $host = Str::make((string)$context->host)->trim()->lower()->val();

        return $host === self::HOST && Val::isNotNull($context->authenticator);
    }

    public function bind(BridgeContext $context): BridgeResult
    {
        $context = Dev::apply(self::HOOK_INPUT, $context);
        Dev::do(self::HOOK_BEFORE, [$context, $this]);

        try {
            $result = $this->bindContext($context);
        } catch (Throwable $exception) {
            $result = $this->rejected(
                $context,
                'bridge_exception',
                ['error_type' => $exception::class]
            );
        }

        $result = Dev::apply(self::HOOK_OUTPUT, $result);
        Dev::do(self::HOOK_AFTER, [$result, $this]);

        return $result;
    }

    private function bindContext(BridgeContext $context): BridgeResult
    {
        if (!$this->supports($context)) {
            $reason = Str::make((string)$context->host)->trim()->lower()->val() !== self::HOST
                ? 'unsupported_host'
                : 'missing_authenticator';

            return $this->rejected($context, $reason);
        }

        $authenticator = $context->authenticator;
        if (!Func::isCallable([$authenticator, 'isAuthenticated'])) {
            return $this->rejected($context, 'invalid_authenticator');
        }

        if (!Flag::parseBool($authenticator->isAuthenticated())) {
            return $this->rejected($context, 'unauthenticated');
        }

        $principalId = $this->principalId($authenticator);
        if (Val::isEmpty($principalId)) {
            return $this->rejected($context, 'missing_principal_id');
        }

        $sessionId = $this->sessionId($context->session, $context);
        if (Val::isEmpty($sessionId)) {
            return $this->rejected($context, 'missing_session_id', [
                'principal_id' => $principalId,
            ]);
        }

        $principal = $this->principalMapper->call($authenticator, $context);
        if (!$principal instanceof PrincipalInterface) {
            return $this->rejected($context, 'invalid_principal_mapping');
        }

        $sessionMapping = Arr::toArray(
            $this->sessionMapper->call($context->session, $principal, $context),
            true
        );
        $session = $sessionMapping['session'] ?? null;
        $participant = $sessionMapping['participant'] ?? null;

        if (!$session instanceof SessionInterface || !$participant instanceof Participant) {
            return $this->rejected($context, 'invalid_session_mapping', [
                'principal_id' => $principal->id(),
                'session_id' => $sessionId,
            ]);
        }

        $presenceContext = $this->mapContext($context, $session, $participant);
        $trustContract = $this->mapAnnexManifest($context, $presenceContext);
        if ($trustContract instanceof BridgeResult) {
            return $trustContract;
        }

        $authResult = AuthResult::success($principal, null, [
            'bridge' => $this->name(),
            'host' => self::HOST,
        ]);
        $result = new BridgeResult();
        $result->bound = true;
        $result->context = $presenceContext;
        $result->auth_result = $authResult;
        $result->trust_contract = $trustContract;
        $result->session = $session;
        $result->reason = 'bound';
        $result->metadata = $this->metadata($context, 'bound', true, [
            'principal_id' => $principal->id(),
            'session_id' => $session->id(),
        ]);

        return $result;
    }

    private function mapPrincipal(mixed $authenticator, BridgeContext $context): PrincipalInterface
    {
        $principal = new Principal();
        $principal->id = $this->principalId($authenticator);
        $principal->type = $this->stringValue($authenticator, 'type', 'user');

        foreach ($this->roles($authenticator) as $role) {
            $principal->roles()->add($role, $role);
        }
        foreach ($this->strings($this->value($authenticator, 'permissions', [])) as $permission) {
            $principal->permissions()->add($permission, $permission);
        }

        $metadata = Arr::toArray($context->metadata ?? [], true);
        $principal->attributes = Arr::make([
            'username' => $this->stringValue($authenticator, 'username'),
            'display_name' => $this->displayName($authenticator),
            'group' => $this->stringValue($authenticator, 'group'),
            'tenant_id' => (string)Arr::getPath($metadata, 'tenant_id', ''),
        ])->filter(fn (mixed $value): bool => Val::isNotEmpty($value))->toArray(true);

        return $principal;
    }

    private function mapSession(
        mixed $hostSession,
        PrincipalInterface $principal,
        BridgeContext $context
    ): array {
        if ($hostSession instanceof SessionInterface) {
            $session = $hostSession;
        } else {
            $session = new Session($this->sessionType($hostSession, $context));
            $session->id = $this->sessionId($hostSession, $context);
            $session->metadata = Arr::toArray($context->metadata ?? [], true);
        }

        $participant = new Participant();
        $participant->id = $principal->id();
        $participant->principal = $principal;
        $participant->metadata = Arr::make([
            'bridge' => $this->name(),
            'host' => self::HOST,
        ])->toArray(true);
        $session->addParticipant($participant);

        return Arr::make([
            'session' => $session,
            'participant' => $participant,
        ])->toArray(true);
    }

    private function mapContext(
        BridgeContext $bridgeContext,
        SessionInterface $session,
        Participant $participant
    ): object {
        $context = $bridgeContext->context();
        $metadata = Arr::toArray($bridgeContext->metadata ?? [], true);
        $context->session_id = $session->id();
        $context->session_type = $session->type();
        $context->tenant_id = (string)Arr::getPath($metadata, 'tenant_id', '');
        $context->action = (string)Arr::getPath($metadata, 'action', 'authenticate');
        $context->requested_privilege = (string)Arr::getPath($metadata, 'requested_privilege', '');
        $context->current_privilege = (string)Arr::getPath($metadata, 'current_privilege', '');
        $context->participant = $participant;
        $context->session = $session;
        $context->metadata = $metadata;

        return $context;
    }

    private function mapAnnexManifest(BridgeContext $context, object $presenceContext): mixed
    {
        $manifest = Arr::toArray($context->annex_manifest ?? [], true);
        if (Arr::isEmpty($manifest)) {
            return null;
        }

        $adapter = $this->annexAdapter;
        if (Val::isNull($adapter)) {
            if (!class_exists(AnnexAdapter::class)) {
                return $this->rejected($context, 'annex_adapter_unavailable');
            }
            $adapter = new AnnexAdapter();
        }

        if (!Func::isCallable([$adapter, 'ingest'])) {
            return $this->rejected($context, 'invalid_annex_adapter');
        }

        $contract = $adapter->ingest($manifest);
        if (Func::isCallable([$contract, 'applyTo'])) {
            $contract->applyTo($presenceContext);
        }

        return $contract;
    }

    private function rejected(
        BridgeContext $context,
        string $reason,
        array $details = []
    ): BridgeResult {
        $result = new BridgeResult();
        $result->context = $context->context();
        $result->reason = $reason;
        $result->metadata = $this->metadata($context, $reason, false, $details);

        return $result;
    }

    private function metadata(
        BridgeContext $context,
        string $reason,
        bool $bound,
        array $details = []
    ): array {
        $audit = Arr::make([
            'bridge' => $this->name(),
            'host' => Str::make((string)$context->host)->trim()->lower()->val(),
            'reason' => $reason,
            'bound' => Flag::parseBool($bound),
        ])->merge($details)->toArray(true);

        return Arr::make(Arr::toArray($context->metadata ?? [], true))
            ->merge(['audit' => $audit])
            ->toArray(true);
    }

    private function principalId(mixed $authenticator): string
    {
        $id = $this->stringValue($authenticator, 'id');

        return Val::isNotEmpty($id)
            ? $id
            : $this->stringValue($authenticator, 'username');
    }

    private function displayName(mixed $authenticator): string
    {
        $displayName = $this->stringValue($authenticator, 'displayname');

        return Val::isNotEmpty($displayName)
            ? $displayName
            : $this->stringValue($authenticator, 'display_name');
    }

    private function roles(mixed $authenticator): array
    {
        return Arr::make($this->strings($this->value($authenticator, 'roles', [])))
            ->merge($this->strings($this->value($authenticator, 'role', [])))
            ->merge($this->strings($this->value($authenticator, 'group', [])))
            ->unique()
            ->values()
            ->toArray(true);
    }

    private function sessionId(mixed $session, BridgeContext $context): string
    {
        if ($session instanceof SessionInterface) {
            return Str::make($session->id())->trim()->val();
        }

        $id = $this->stringValue($session, 'id');
        if (Val::isEmpty($id)) {
            $id = $this->stringValue($session, 'session_id');
        }
        if (Val::isEmpty($id)) {
            $id = (string)Arr::getPath(
                Arr::toArray($context->metadata ?? [], true),
                'session_id',
                ''
            );
        }

        return Str::make($id)->trim()->val();
    }

    private function sessionType(mixed $session, BridgeContext $context): string
    {
        if ($session instanceof SessionInterface) {
            return Str::make($session->type())->trim()->val();
        }

        $type = $this->stringValue($session, 'type');
        if (Val::isEmpty($type)) {
            $type = (string)Arr::getPath(
                Arr::toArray($context->metadata ?? [], true),
                'session_type',
                'session'
            );
        }

        return Str::make($type)->trim()->val();
    }

    private function stringValue(mixed $source, string $field, string $default = ''): string
    {
        return Str::make((string)$this->value($source, $field, $default))->trim()->val();
    }

    private function value(mixed $source, string $field, mixed $default = null): mixed
    {
        if ($source instanceof Obj) {
            $value = $source->field($field);

            return Val::isNotNull($value) ? $value : $default;
        }

        if (Arr::is($source)) {
            return Arr::getPath($source, $field, $default);
        }

        if (is_object($source)) {
            return $source->{$field} ?? $default;
        }

        return $default;
    }

    private function strings(mixed $value): array
    {
        if ($value instanceof Collection) {
            $items = $value->toArray(true);
        } elseif ($value instanceof Arr) {
            $items = $value->toArray(true);
        } elseif (Arr::is($value)) {
            $items = Arr::toArray($value, true);
        } elseif (Val::isNotEmpty($value)) {
            $items = [$value];
        } else {
            $items = [];
        }

        return Arr::make($items)
            ->map(fn (mixed $item): string => Str::make((string)$item)->trim()->val())
            ->filter(fn (string $item): bool => Val::isNotEmpty($item))
            ->unique()
            ->values()
            ->toArray(true);
    }
}
