<?php

return [
    // The graphics engine asked for without a name: 'velvet' on the CPU, or one a package
    // brings ('metal', 'opengl', 'vulkan', 'sdl3').
    'default' => env('GRAPHICS_ENGINE', 'velvet'),
];
