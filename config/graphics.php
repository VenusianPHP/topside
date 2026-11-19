<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Graphics Engine
    |--------------------------------------------------------------------------
    |
    | Default: the engine app('gfx')->engine() gives without a name: the name
    | a package registers ('velvet', 'blend2d', 'thorvg', ...).
    |
    | GPU: whether that engine is asked to draw on the GPU. An engine that
    | draws on the CPU only refuses true.
    |
    */

    'engine' => [
        'default' => env('GRAPHICS_ENGINE', 'velvet'),
        'gpu' => (bool) env('GRAPHICS_GPU', false),
    ],

];
