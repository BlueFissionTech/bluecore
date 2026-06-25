<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use BlueFission\BlueCore\Menu;
use BlueFission\BlueCore\MenuItem;

function bluecore_example_menu_payload(Menu $menu): array
{
    $items = [];

    foreach ($menu->getItems() as $item) {
        if ($item instanceof Menu) {
            $items[] = bluecore_example_menu_payload($item);
            continue;
        }

        $items[] = [
            'type' => 'item',
            'id' => $item->getId(),
            'label' => $item->getLabel(),
            'action' => $item->getAction(),
            'role' => $item->getRole(),
            'group' => $item->getGroup(),
            'permission' => $item->getPermission(),
        ];
    }

    return [
        'type' => 'menu',
        'id' => $menu->getId(),
        'label' => $menu->getLabel(),
        'items' => $items,
    ];
}

$workspace = new Menu('Workspace');
$workspace->addItem(new MenuItem('Dashboard', '/dashboard', 'operator', 'workspace', 'dashboard.view'));
$workspace->addItem(new MenuItem('Tasks', '/tasks', 'operator', 'workspace', 'tasks.manage'));

$reports = new Menu('Reports');
$reports->addItem(new MenuItem('Activity', '/reports/activity', 'analyst', 'reports', 'reports.view'));
$reports->addItem(new MenuItem('Exports', '/reports/exports', 'analyst', 'reports', 'reports.export'));

$workspace->addItem($reports);

bluecore_example_json(bluecore_example_menu_payload($workspace));
