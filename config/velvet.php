<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CPU Backend Drivers
    |--------------------------------------------------------------------------
    |
    | On the CPU, Velvet is built from Topside's rendering parts. Here you may
    | name the driver Velvet uses for each part: "native" (PHP), "ext" (C), a
    | library binding your application has installed, or "auto" to take the
    | first of those that can run.
    |
    | Each part is "auto" unless you say otherwise. What you name here changes
    | Velvet alone: code that asks Rendering for the part itself still gets
    | the default from config/rendering.php. Set a part to null to have Velvet
    | follow that default too.
    |
    */

    'cpu' => [

        /*
        |----------------------------------------------------------------------
        | Framebuffer Driver
        |----------------------------------------------------------------------
        |
        | What holds the pixels of the framebuffers Velvet hands out.
        |
        */

        'framebuffer' => env('VELVET_FRAMEBUFFER','auto'),

        /*
        |----------------------------------------------------------------------
        | Rasterizer Driver
        |----------------------------------------------------------------------
        |
        | What turns a shape into pixels for the rasterizer Velvet hands out.
        | A painter brings a rasterizer of its own, so this does not change
        | what the painter draws with: name the painter driver for that.
        |
        */

        'rasterizer' => env('VELVET_RASTERIZER','auto'),

        /*
        |----------------------------------------------------------------------
        | Painter Driver
        |----------------------------------------------------------------------
        |
        | What carries out the frames drawn with the painters Velvet hands out.
        |
        */

        'painter' => env('VELVET_PAINTER','auto'),

        /*
        |----------------------------------------------------------------------
        | Raycaster Driver
        |----------------------------------------------------------------------
        |
        | What casts the rays of the raycasters Velvet hands out. Raycasting is
        | off unless config/rendering.php's raycaster.enabled turns it on, and
        | this option does not turn it on.
        |
        */

        'raycaster' => env('VELVET_RAYCASTER', 'auto'),

    ],

];
