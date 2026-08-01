<?php

return [
    'nav_label' => 'Users',
    'singular' => 'User',
    'plural' => 'Users',

    'fields' => [
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'password' => 'Password',
        'role' => 'Role',
        'warehouse' => 'Warehouse (branch assignment)',
        'warehouse_help' => 'Leave empty for Admin/Manager (they are not branch-locked).',
        'is_active' => 'Active',
    ],
];
