<?php

return [
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),
    'admin' => [
        'name' => env('SMARTBITE_ADMIN_NAME', 'Smartbite Administrator'),
        'email' => env('SMARTBITE_ADMIN_EMAIL'),
        'password' => env('SMARTBITE_ADMIN_PASSWORD'),
    ],
];
