<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Lustrous Renderer Configuration
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
        |--------------------------------------------------------------------------
        | Framebuffers
        |--------------------------------------------------------------------------
        | Framebuffers are <placeholder_pretend_explanation>
        |
        | Available - 'lustrous-c', 'lustrous-php', 'auto'
        | Supported w/ modules - 'pixman', 'sdl3', 'thor-vg', 'pluto-vg', 'blend2d'
        | Still looking into - 'opengl', 'vulkan'
        */
        'framebuffers' => [
            'default' => env('VELVET_FRAMEBUFFER','lustrous-php-full'),

            // Lustrous: 'lustrous-php-<kind>' and 'lustrous-c-<kind>', kind one of 'full', 'dirty',
            // 'epaper', 'paged', 'ring'. Paged and ring frames take these when made by name.
            'lustrous' => [
                'page_rows' => 0,   // rows per page of a paged frame; 0 for one page of the whole height
                'frames' => 2,      // frames in a ring
            ],
        ],
        /*
        |--------------------------------------------------------------------------
        | Rasterizers
        |--------------------------------------------------------------------------
        | Rasterizers contain all those fun and exciting effects use all that crazy
        | math you had to learn in school but forgot, that make your application
        | visually appealing while its eat all your compute, Really tho, they
        | calculate effects like shadows and update the framebuffers to show this.
        |
        | Available - 'lustrous-c', 'lustrous-php', 'auto'
        | Supported w/ modules - 'pixman', 'sdl3', 'thor-vg', 'pluto-vg', 'blend2d'
        | Still looking into - 'opengl', 'vulkan'
        */
        'rasterizers' => [
            'default' => env('VELVET_RASTERIZER','auto'),
        ],
        /*
        |--------------------------------------------------------------------------
        | Paint Brushes
        |--------------------------------------------------------------------------
        | What do you do with a paint brush? You stroke it! How do you make
        | a scene paint? With math of course! Add the lights, the colors, the
        | shapes and the fonts using a digital paint brush implemented
        | by any of these fine drivers (if you have them installed)
        |
        | Available - 'lustrous-c', 'lustrous-php', 'auto'
        | Supported w/ modules - 'pixman', 'sdl3', 'thor-vg', 'pluto-vg', 'blend2d'
        | Still looking into - 'opengl', 'vulkan'
        */
        'painters' => [
            'default' => env('VELVET_PAINTER','auto'),
        ],
        /*
        |--------------------------------------------------------------------------
        | Advanced Rendering Techniques
        |--------------------------------------------------------------------------
        | Plug them in here.
        | Raycasters, path tracing, Tile Mappers, Post Processing, etc
        |
        */
        'advanced' => [
            //'raycasting' => ['default' => 'lustrous-c']
        ]
    ],
    'gpu' => [
        'placeholder' => 'mindbrik'
    ],

];