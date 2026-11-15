<?php

return [
    // 'auto' takes the first driver that can run: a registered library binding, then 'ext'
    // (VelvetGE in C), then 'native' (VelvetGE in PHP). Or name one driver to use only it.
    'rasterizer' => [
        'default' => 'auto',
    ],

    'framebuffer' => [
        'default' => 'auto',
    ],

    // What turns a polyline into a stroke: the stroking driver a package registers, by name. Lustrous
    // registers 'lustrous-php', 'lustrous-c' and 'auto' (lustrous-c where ext-rasterize can stroke,
    // else lustrous-php); Blend2D, ThorVG and PlutoVG register 'blend2d', 'thorvg' and 'plutovg'.
    'stroker' => [
        'default' => 'auto',
    ],

    'painter' => [
        'default' => 'auto',
    ],

    // What a painter records a frame with, and compares it to the frame before with.
    'recorder' => [
        'default' => 'auto',
    ],
];