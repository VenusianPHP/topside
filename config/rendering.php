<?php

return [
    'recorder' => [
        'default' => env('RENDERING_RECORDER', 'auto'),
    ],

    'frames' => [
        'default' => env('RENDERING_FRAMES'),
    ],

    'drawers' => [
        'default' => env('RENDERING_DRAWERS'),
    ],

    /*
    'stroker' => [
        'default' => env('RENDERING_STROKER','auto'),
    ],

    // What a painter records a frame with, and compares it to the frame before with.


    'rasterizer' => [
        'default' => env('RENDERING_RASTERIZER','auto'),
    ],

    'framebuffer' => [
        'default' => env('RENDERING_FRAMEBUFFER','auto'),
    ],

    'painter' => [
        'default' => env('RENDERING_PAINTER','auto'),
    ],
    // What draws the glyphs of text: a glyph driver a package registers, by name ('velvet' is Velvet's).
    'glyphs' => [
        'default' => env('RENDERING_GLYPHS','auto'),
    ],
    */
];