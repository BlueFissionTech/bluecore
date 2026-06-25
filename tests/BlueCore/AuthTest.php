<?php

namespace BlueFission\Tests\BlueCore;

use BlueFission\BlueCore\Auth;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    public function testHasPermissionUsesArrayBackedPermissions(): void
    {
        $auth = $this->authWithPermissions(['reports.view', 'tasks.manage']);

        $this->assertTrue($auth->hasPermission('reports.view'));
        $this->assertFalse($auth->hasPermission('users.manage'));
    }

    private function authWithPermissions(array $permissions): Auth
    {
        return new class($permissions) extends Auth {
            public function __construct(private array $permissions)
            {
            }

            public function hasPermission(string $permission): bool
            {
                $this->_data['permissions'] = $this->permissions;

                return parent::hasPermission($permission);
            }
        };
    }
}
