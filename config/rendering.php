<?php

return [
    'stroker' => [
        'default' => env('TOPSIDE_STROKER','auto'),
    ],

    // What a painter records a frame with, and compares it to the frame before with.
    'recorder' => [
        'default' => env('TOPSIDE_RECORDER','auto'),
    ],

    'rasterizer' => [
        'default' => env('TOPSIDE_RASTERIZER','auto'),
    ],

    'framebuffer' => [
        'default' => env('TOPSIDE_FRAMEBUFFER','auto'),
    ],

    'painter' => [
        'default' => env('TOPSIDE_PAINTER','auto'),
    ],
    // What draws the glyphs of text: a glyph driver a package registers, by name ('velvet' is Velvet's).
    'glyphs' => [
        'default' => env('TOPSIDE_GLYPHS','auto'),
    ],
];