<?php

namespace BlueFission\BlueCore\Integration\Presence;

use BlueFission\Arr;
use BlueFission\Func;
use BlueFission\Presence\Auth\AuthResult;
use BlueFission\Presence\Auth\Principal;
use BlueFission\Presence\Auth\PrincipalInterface;
use BlueFission\Presence\Bridge\BridgeContext;
use BlueFission\Presence\Bridge\BridgeInterface;
use BlueFission\Presence\Bridge\BridgeResult;
use BlueFission\Presence\Session\Participant;
use BlueFission\Presence\Session\Session;
use BlueFission\Presence\Session\SessionInterface;

class PresenceBridgeFactory
{
    private const CONTRACTS = [
        BridgeInterface::class => 'interface',
        BridgeContext::class => 'class',
        BridgeResult::class => 'class',
        PrincipalInterface::class => 'interface',
        Principal::class => 'class',
        AuthResult::class => 'class',
        SessionInterface::class => 'interface',
        Session::class => 'class',
        Participant::class => 'class',
    ];

    public static function available(): bool
    {
        return Arr::isEmpty(self::missingContracts());
    }

    public static function missingContracts(): array
    {
        $missing = Arr::make();

        foreach (self::CONTRACTS as $contract => $type) {
            $available = $type === 'interface'
                ? interface_exists($contract)
                : class_exists($contract);

            if (!$available) {
                $missing->push($contract);
            }
        }

        return Arr::toArray($missing, true);
    }

    public static function make(
        ?Func $principalMapper = null,
        ?Func $sessionMapper = null,
        ?object $annexAdapter = null
    ): object {
        $missing = self::missingContracts();

        if (Arr::isNotEmpty($missing)) {
            throw new PresenceBridgeUnavailable($missing);
        }

        return new PresenceAuthenticationBridge(
            $principalMapper,
            $sessionMapper,
            $annexAdapter
        );
    }
}
