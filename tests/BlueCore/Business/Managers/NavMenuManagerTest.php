<?php

namespace BlueFission\Tests\BlueCore\Business\Managers;

use BlueFission\Arr;
use BlueFission\BlueCore\Auth;
use BlueFission\BlueCore\Business\Managers\NavMenuManager;
use BlueFission\BlueCore\MenuItem;
use PHPUnit\Framework\TestCase;

class NavMenuManagerTest extends TestCase
{
    public function testRenderMenuJoinsAllowedItemsAndSkipsUnauthorizedItems(): void
    {
        $manager = new NavMenuManager(new FakeMenuAuthenticator(
            roles: ['admin'],
            groups: ['staff'],
            permissions: ['reports.view']
        ));

        $menu = new FakeMenu('main');
        $menu->addItem(new FakeMenuItem('Allowed', '/allowed', role: 'admin'));
        $menu->addItem(new FakeMenuItem('Denied Role', '/denied-role', role: 'owner'));
        $menu->addItem(new FakeMenuItem('Allowed Permission', '/allowed-permission', permission: 'reports.view'));
        $menu->addItem(new FakeMenuItem('Denied Permission', '/denied-permission', permission: 'reports.edit'));

        $manager->registerMenu($menu);

        $this->assertSame(
            '<item>Allowed</item>' . "\n" . '<item>Allowed Permission</item>',
            $manager->renderMenu('main')
        );
    }

    public function testConditionalDisplayHelpersRenderMatchingItems(): void
    {
        $manager = new NavMenuManager(new FakeMenuAuthenticator());
        $menu = new FakeMenu('settings');
        $menu->addItem(new FakeMenuItem('Users', '/users', role: 'admin', group: 'staff', permission: 'users.manage'));
        $manager->registerMenu($menu);

        $this->assertSame('<item>Users</item>', $manager->displayMenuItemBasedOnRole('settings', 'users', 'admin'));
        $this->assertSame('<item>Users</item>', $manager->displayMenuItemBasedOnGroup('settings', 'users', 'staff'));
        $this->assertSame('<item>Users</item>', $manager->displayMenuItemBasedOnPermission('settings', 'users', 'users.manage'));
        $this->assertSame('', $manager->displayMenuItemBasedOnRole('settings', 'users', 'operator'));
    }
}

class FakeMenu
{
    private array $items = [];

    public function __construct(private string $id)
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function addItem(MenuItem $item): void
    {
        $this->items[$item->getId()] = $item;
    }

    public function getItem(string $itemId): ?MenuItem
    {
        return $this->items[$itemId] ?? null;
    }

    public function getItems(): array
    {
        return $this->items;
    }
}

class FakeMenuItem extends MenuItem
{
    public function render(): string
    {
        return '<item>' . $this->getLabel() . '</item>';
    }
}

class FakeMenuAuthenticator extends Auth
{
    public function __construct(
        private array $roles = [],
        private array $groups = [],
        private array $permissions = []
    ) {
    }

    public function hasRole(string $role): bool
    {
        return Arr::has($this->roles, $role, true);
    }

    public function isInGroup(string $group): bool
    {
        return Arr::has($this->groups, $group, true);
    }

    public function hasPermission(string $permission): bool
    {
        return Arr::has($this->permissions, $permission, true);
    }
}
