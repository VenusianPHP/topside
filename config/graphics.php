<?php

return [
    'engine' => [
        // The graphics engine asked for without a name: 'velvet' on the CPU, or one a package
        // brings ('metal', 'opengl', 'vulkan', 'sdl3').
        'default' => env('GRAPHICS_ENGINE', 'velvet'),

        // What computes the pixels: 'cpu', 'gpu', or 'auto' (the GPU where the engine has one,
        // otherwise the CPU). An engine refuses a backend it does not offer.
        'backend' => env('GRAPHICS_BACKEND', 'auto'),
    ]
];
