<?php

namespace {
    require_once dirname(__DIR__, 3)
        . DIRECTORY_SEPARATOR . 'Fixtures'
        . DIRECTORY_SEPARATOR . 'presence-contracts.php';
}

namespace BlueFission\Tests\BlueCore\Integration\Presence {
    use BlueFission\Arr;
    use BlueFission\BlueCore\Integration\Presence\PresenceAuthenticationBridge;
    use BlueFission\BlueCore\Integration\Presence\PresenceBridgeFactory;
    use BlueFission\DevElation as Dev;
    use BlueFission\Presence\Bridge\BridgeContext;
    use BlueFission\Presence\Bridge\BridgeInterface;
    use BlueFission\Str;
    use BlueFission\System\Process;
    use BlueFission\Utils\Path;
    use PHPUnit\Framework\TestCase;

    class PresenceAuthenticationBridgeTest extends TestCase
    {
        protected function tearDown(): void
        {
            $this->resetDevElation();
        }

        public function testFactoryCreatesBridgeWhenPresenceContractsAreAvailable(): void
        {
            $this->assertTrue(PresenceBridgeFactory::available());
            $this->assertInstanceOf(
                BridgeInterface::class,
                PresenceBridgeFactory::make()
            );
        }

        public function testBridgeMapsIdentitySessionManifestAndLifecycleHooks(): void
        {
            $events = [];
            Dev::up();
            Dev::filter(PresenceAuthenticationBridge::HOOK_INPUT, function (mixed $value) use (&$events): mixed {
                $events[] = 'input';

                return $value;
            });
            Dev::action(PresenceAuthenticationBridge::HOOK_BEFORE, function () use (&$events): void {
                $events[] = 'before';
            });
            Dev::filter(PresenceAuthenticationBridge::HOOK_OUTPUT, function (mixed $value) use (&$events): mixed {
                $events[] = 'output';

                return $value;
            });
            Dev::action(PresenceAuthenticationBridge::HOOK_AFTER, function () use (&$events): void {
                $events[] = 'after';
            });

            $context = $this->context();
            $result = (new PresenceAuthenticationBridge())->bind($context);

            $this->assertTrue($result->isBound());
            $this->assertSame('bound', $result->reason);
            $this->assertTrue($result->auth_result->isSuccessful());
            $this->assertSame('user-42', $result->auth_result->principal->id());
            $this->assertSame(
                ['admin' => 'admin', 'operations' => 'operations'],
                $result->auth_result->principal->roles()->toArray(true)
            );
            $this->assertSame(
                ['reports.view' => 'reports.view', 'users.manage' => 'users.manage'],
                $result->auth_result->principal->permissions()->toArray(true)
            );
            $this->assertSame('session-7', $result->session->id());
            $this->assertSame('tenant-a', $result->context->tenant_id);
            $this->assertSame('session-7', $result->context->session_id);
            $this->assertSame(
                $result->trust_contract,
                $result->context->policy('trust_contract')
            );
            $this->assertSame('user-42', $result->metadata['audit']['principal_id']);
            $this->assertSame('session-7', $result->metadata['audit']['session_id']);
            $this->assertSame(['input', 'before', 'output', 'after'], $events);
        }

        public function testBridgeReturnsStructuredRejectionsForIncompleteContexts(): void
        {
            $bridge = new PresenceAuthenticationBridge();

            $unsupported = $this->context();
            $unsupported->host = 'other';
            $this->assertRejected($bridge->bind($unsupported), 'unsupported_host');

            $missingAuthenticator = $this->context();
            $missingAuthenticator->authenticator = null;
            $this->assertRejected($bridge->bind($missingAuthenticator), 'missing_authenticator');

            $unauthenticated = $this->context(authenticated: false);
            $this->assertRejected($bridge->bind($unauthenticated), 'unauthenticated');

            $missingPrincipal = $this->context();
            $missingPrincipal->authenticator->id = '';
            $missingPrincipal->authenticator->username = '';
            $this->assertRejected($bridge->bind($missingPrincipal), 'missing_principal_id');

            $missingSession = $this->context();
            $missingSession->session = [];
            $missingSession->metadata = [];
            $this->assertRejected($bridge->bind($missingSession), 'missing_session_id');
        }

        public function testFactoryReportsMissingOptionalContractsWithoutFatalLoading(): void
        {
            $root = Path::normalize(dirname(__DIR__, 4));
            $fixture = Path::normalize(
                $root
                . DIRECTORY_SEPARATOR . 'tests'
                . DIRECTORY_SEPARATOR . 'Fixtures'
                . DIRECTORY_SEPARATOR . 'presence-bridge-unavailable.php'
            );
            $command = Str::make(escapeshellarg(PHP_BINARY))
                ->append(' ')
                ->append(escapeshellarg($fixture))
                ->val();
            $process = new Process($command, $root, [], [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ]);

            $process->start();
            while ($process->status() === true) {
                usleep(10000);
            }

            $output = $process->output();
            $exitCode = $process->close();
            $result = Arr::toArray(
                json_decode($output, true, flags: JSON_THROW_ON_ERROR),
                true
            );

            $this->assertSame(0, $exitCode, $output);
            $this->assertFalse($result['available']);
            $this->assertSame('presence_contracts_unavailable', $result['reason']);
            $this->assertNotEmpty($result['details']['missing_contracts']);
        }

        private function context(bool $authenticated = true): BridgeContext
        {
            $context = new BridgeContext();
            $context->host = 'bluecore';
            $context->authenticator = new class ($authenticated) {
                public string $id = 'user-42';
                public string $username = 'ada';
                public string $displayname = 'Ada Lovelace';
                public string $role = 'admin';
                public string $group = 'operations';
                public array $permissions = ['reports.view', 'users.manage'];

                public function __construct(private bool $authenticated)
                {
                }

                public function isAuthenticated(): bool
                {
                    return $this->authenticated;
                }
            };
            $context->session = [
                'id' => 'session-7',
                'type' => 'browser',
            ];
            $context->annex_manifest = [
                'strictness_level' => 2,
                'requested_scope' => ['profile.read'],
            ];
            $context->metadata = [
                'tenant_id' => 'tenant-a',
                'action' => 'login',
                'requested_privilege' => 'account.read',
            ];

            return $context;
        }

        private function assertRejected(object $result, string $reason): void
        {
            $this->assertFalse($result->isBound());
            $this->assertSame($reason, $result->reason);
            $this->assertSame($reason, $result->metadata['audit']['reason']);
            $this->assertFalse($result->metadata['audit']['bound']);
        }

        private function resetDevElation(): void
        {
            Dev::down();
            $reflection = new \ReflectionClass(Dev::class);

            foreach (['_filters', '_actions'] as $propertyName) {
                $property = $reflection->getProperty($propertyName);
                $property->setValue(null, []);
            }
        }
    }
}
