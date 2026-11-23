<?php
$groups = [0 => ['/portal' => 'Dashboard', '/portal/projects' => 'Projects', '/portal/orders' => 'Orders & billing', '/portal/documents' => 'Documents', '/portal/support' => 'Support', '/portal/assistant' => 'AI workspace', '/portal/account' => 'Account & privacy'],
  'Shop' => ['/services' => 'Browse services', '/cart' => 'Cart']];
if (App\Auth::isStaff($user ?? null)) $groups['Team'] = ['/admin' => 'Control Tower'];
$area = 'portal'; $areaName = 'Client portal'; $home = '/portal';
include APP_ROOT . '/views/partials/console-shell.php';
