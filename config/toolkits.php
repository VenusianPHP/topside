<?php

return [
    'bridge' => [
        'mac' => [
            'default' => env('TOOLKIT_BRIDGE_DEFAULT', 'appkit'), //also 'gtk' or 'qt'
        ],
        'linux' => [
            'default' => env('TOOLKIT_BRIDGE_DEFAULT', 'gtk'), //or 'qt'
        ]
    ],
];