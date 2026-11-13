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

    // What turns a polyline into a stroke: 'auto' takes ext-rasterize's stroker when it has one and
    // PHP's otherwise; 'ext' insists on ext-rasterize's; 'native' is PHP's.
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

    // Raycasting is off unless an application asks for it: with 'enabled' false no raycasting
    // driver is registered and Raycasters refuses, saying how to turn it on.
    'raycaster' => [
        'enabled' => (bool) env('RENDERING_RAYCASTER', false),
        'default' => 'auto',
    ],
];