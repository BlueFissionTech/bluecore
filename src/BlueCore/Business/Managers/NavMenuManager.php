<?php
namespace BlueFission\BlueCore\Business\Managers;

use BlueFission\Arr;
use BlueFission\Connections\Database\MySQLLink;
use BlueFission\Services\Service;
use BlueFission\BlueCore\Auth as Authenticator;
use BlueFission\BlueCore\MenuItem;
use BlueFission\Val;

class NavMenuManager extends Service
{
    protected $_menus = [];
	protected $_authenticator;

    public function __construct(Authenticator $authenticator)
    {
        parent::__construct();
        $this->_authenticator = $authenticator;
    }

    public function registerMenu($menu)
    {
        // Ensure menu is not already registered
        if (!Arr::hasKey($this->_menus, $menu->getId())) {
            $this->_menus[$menu->getId()] = $menu;
        }
        // $this->_menus[$menu->getName()] = $menu;
    }

    public function getMenu($menuId)
    {
        if (Arr::hasKey($this->_menus, $menuId)) {
            return $this->_menus[$menuId];
        }
        return null;
    }

    public function addMenuItem($menuId, $menuItem)
    {
        if (Arr::hasKey($this->_menus, $menuId)) {
            $this->_menus[$menuId]->addItem($menuItem);
        }
    }

    public function renderMenu(string $menuName)
    {
        $renderedItems = [];
        if (Arr::hasKey($this->_menus, $menuName)) {
            $menu = $this->_menus[$menuName];
            $menuItems = $menu->getItems();

            foreach ($menuItems as $item) {
                if ( $item instanceof MenuItem ) {
                    $requiredRole = $item->getRole();
                    $requiredGroup = $item->getGroup();
                    $requiredPermission = $item->getPermission();

                    // Check role, group, and permission against current user
                    if (
                        (Val::isNotEmpty($requiredRole) && !$this->_authenticator->hasRole($requiredRole)) ||
                        (Val::isNotEmpty($requiredGroup) && !$this->_authenticator->isInGroup($requiredGroup)) ||
                        (Val::isNotEmpty($requiredPermission) && !$this->_authenticator->hasPermission($requiredPermission))
                    ) {
                        continue; // Skip this item if user does not meet requirements
                    }
                }

                // Render the menu item
                $renderedItems[] = $item->render();
            }
        }

        return Arr::make($renderedItems)->join("\n")->val();
    }

    public function displayMenuItemBasedOnRole($menuId, $itemId, $role)
    {
        if (Arr::hasKey($this->_menus, $menuId)) {
            $menuItem = $this->_menus[$menuId]->getItem($itemId);
            if ($menuItem->getRole() === $role) {
                return $menuItem->render();
            }
        }
        return '';
    }

    public function displayMenuItemBasedOnGroup($menuId, $itemId, $group)
    {
        if (Arr::hasKey($this->_menus, $menuId)) {
            $menuItem = $this->_menus[$menuId]->getItem($itemId);
            if ($menuItem && $menuItem->getGroup() === $group) {
                return $menuItem->render();
            }
        }
        return '';
    }

    public function displayMenuItemBasedOnPermission($menuId, $itemId, $permission)
    {
        if (Arr::hasKey($this->_menus, $menuId)) {
            $menuItem = $this->_menus[$menuId]->getItem($itemId);
            if ($menuItem && $menuItem->getPermission() === $permission) {
                return $menuItem->render();
            }
        }
        return '';
    }
}
