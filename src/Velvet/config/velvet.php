<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Lustrous Renderer Configuration
    |--------------------------------------------------------------------------
    |
    | On the CPU, Velvet is built from Topside's rendering parts. Here you may
    | name the driver Velvet uses for each part: one of Lustrous's ("lustrous-php"
    | in plain PHP, "lustrous-c" in C) or one a module your application has
    | installed registers. What you name here changes Velvet alone: code that
    | asks Rendering for a part by its own name still gets Rendering's default.
    |
    */
    'cpu' => [
        /*
        |--------------------------------------------------------------------------
        | Framebuffers
        |--------------------------------------------------------------------------
        | Framebuffers hold the pixels everything else draws into, and hand
        | them to whatever shows them.
        |
        | Available - 'lustrous-php', 'lustrous-c' (a full frame), or a kind
        |             by name: 'lustrous-php-<kind>', 'lustrous-c-<kind>' with
        |             kind one of 'full', 'dirty', 'epaper', 'paged', 'ring'
        | Supported w/ modules - 'pixman', 'sdl3', 'thorvg', 'plutovg', 'blend2d'
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
        |-------------------z2-------------------------------------------------------
        | Rasterizers contain all those fun and exciting effects use all that crazy
        | math you had to learn in school but forgot, that make your application
        | visually appealing while its eat all your compute, Really tho, they
        | calculate effects like shadows and update the framebuffers to show this.
        |
        | Available - 'lustrous-c', 'lustrous-php', 'auto' (lustrous-c where
        |             ext-rasterize is loaded, else lustrous-php)
        | Supported w/ modules - 'thorvg', 'plutovg', 'blend2d'
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
        | Available - 'lustrous-c', 'lustrous-php', 'auto' (lustrous-c where
        |             ext-paint is loaded, else lustrous-php)
        | Supported w/ modules - 'plutovg'
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
        | Raycasting - 'lustrous-c', 'lustrous-php', 'auto' (lustrous-c where
        |              ext-raycast is loaded, else lustrous-php)
        */
        'advanced' => [
            'raycasting' => [
                'default' => env('VELVET_RAYCASTER', 'auto'),
            ],
        ]
    ],
    'gpu' => [
        'placeholder' => 'mindbrik'
    ],

];