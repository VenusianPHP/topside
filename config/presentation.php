<?php

return [
    /*
        |----------------------------------------------------------------------
        | Presentation Mode
        |----------------------------------------------------------------------
        |
        | The build your application opens:
        |
        | 'toolkit' - a toolkit-driven window, with canvases drawn by the
        |             canvas driver below.
        |
        | Supported: "toolkit"
        |
        */
    'mode' => env('PRESENTATION_MODE', 'toolkit'),
    'toolkit' => [
        'driver' => env(
            'PRESENTATION_TOOLKIT_DRIVER',
            env(
                'TOOLKIT_BRIDGE_DEFAULT',
                device_os_family() == 'mac' ? 'appkit' : 'gtk'
            )
        ),
        // the graphics engine a canvas is drawn with: overrides GRAPHICS_ENGINE, as PRESENTATION_TOOLKIT_DRIVER does TOOLKIT_BRIDGE_DEFAULT
        'canvas_driver' => env('PRESENTATION_CANVAS_DRIVER', env('GRAPHICS_ENGINE', 'velvet')),
    ],
];
