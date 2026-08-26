<?php

namespace BlueFission\Presence\Support {
    if (!class_exists(Context::class)) {
        class Context
        {
            public ?string $action = null;
            public ?string $session_id = null;
            public ?string $session_type = null;
            public ?string $tenant_id = null;
            public ?string $requested_privilege = null;
            public ?string $current_privilege = null;
            public mixed $participant = null;
            public mixed $session = null;
            public array $metadata = [];
            public array $policies = [];

            public function policy(string $key, mixed $value = null): mixed
            {
                if ($value === null) {
                    return $this->policies[$key] ?? null;
                }

                $this->policies[$key] = $value;

                return $this;
            }
        }
    }
}

namespace BlueFission\Presence\Bridge {
    use BlueFission\Presence\Support\Context;

    if (!interface_exists(BridgeInterface::class)) {
        interface BridgeInterface
        {
            public function name(): string;
            public function supports(BridgeContext $context): bool;
            public function bind(BridgeContext $context): BridgeResult;
        }
    }

    if (!class_exists(BridgeContext::class)) {
        class BridgeContext
        {
            public ?string $host = null;
            public mixed $request = null;
            public array $arguments = [];
            public mixed $session = null;
            public mixed $authenticator = null;
            public array $annex_manifest = [];
            public ?Context $presence_context = null;
            public array $metadata = [];

            public function context(): Context
            {
                return $this->presence_context ??= new Context();
            }
        }
    }

    if (!class_exists(BridgeResult::class)) {
        class BridgeResult
        {
            public bool $bound = false;
            public mixed $context = null;
            public mixed $auth_result = null;
            public mixed $trust_contract = null;
            public mixed $session = null;
            public ?string $reason = null;
            public array $metadata = [];

            public function isBound(): bool
            {
                return $this->bound;
            }
        }
    }
}

namespace BlueFission\Presence\Auth {
    use BlueFission\Collections\Collection;

    if (!interface_exists(PrincipalInterface::class)) {
        interface PrincipalInterface
        {
            public function id(): string;
            public function type(): string;
            public function roles(): Collection;
            public function permissions(): Collection;
        }
    }

    if (!class_exists(Principal::class)) {
        class Principal implements PrincipalInterface
        {
            public ?string $id = null;
            public ?string $type = null;
            public array $attributes = [];
            private Collection $roleCollection;
            private Collection $permissionCollection;

            public function __construct()
            {
                $this->roleCollection = new Collection();
                $this->permissionCollection = new Collection();
            }

            public function id(): string
            {
                return (string)$this->id;
            }

            public function type(): string
            {
                return (string)$this->type;
            }

            public function roles(): Collection
            {
                return $this->roleCollection;
            }

            public function permissions(): Collection
            {
                return $this->permissionCollection;
            }
        }
    }

    if (!class_exists(AuthResult::class)) {
        class AuthResult
        {
            public bool $authenticated = false;
            public mixed $principal = null;
            public mixed $credential = null;
            public ?string $reason = null;
            public array $metadata = [];

            public static function success(
                PrincipalInterface $principal,
                mixed $credential = null,
                array $metadata = []
            ): self {
                $result = new self();
                $result->authenticated = true;
                $result->principal = $principal;
                $result->credential = $credential;
                $result->reason = 'authenticated';
                $result->metadata = $metadata;

                return $result;
            }

            public function isSuccessful(): bool
            {
                return $this->authenticated;
            }
        }
    }
}

namespace BlueFission\Presence\Session {
    use BlueFission\Presence\Auth\PrincipalInterface;

    if (!interface_exists(SessionInterface::class)) {
        interface SessionInterface
        {
            public function id(): string;
            public function type(): string;
            public function addParticipant(Participant $participant): void;
        }
    }

    if (!class_exists(Participant::class)) {
        class Participant
        {
            public ?string $id = null;
            public ?PrincipalInterface $principal = null;
            public array $metadata = [];

            public function id(): string
            {
                return (string)$this->id;
            }
        }
    }

    if (!class_exists(Session::class)) {
        class Session implements SessionInterface
        {
            public ?string $id = null;
            public array $metadata = [];
            public array $participants = [];

            public function __construct(private string $sessionType = 'session')
            {
            }

            public function id(): string
            {
                return (string)$this->id;
            }

            public function type(): string
            {
                return $this->sessionType;
            }

            public function addParticipant(Participant $participant): void
            {
                $this->participants[$participant->id()] = $participant;
            }
        }
    }
}

namespace BlueFission\Presence\Annex {
    use BlueFission\Presence\Support\Context;

    if (!class_exists(TrustContract::class)) {
        class TrustContract
        {
            public array $manifest = [];

            public function applyTo(Context $context): Context
            {
                $context->policy('trust_contract', $this);

                return $context;
            }
        }
    }

    if (!class_exists(AnnexAdapter::class)) {
        class AnnexAdapter
        {
            public function ingest(array $manifest): TrustContract
            {
                $contract = new TrustContract();
                $contract->manifest = $manifest;

                return $contract;
            }
        }
    }
}
