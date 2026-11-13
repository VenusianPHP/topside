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
        'canvas_driver' => env('PRESENTATION_CANVAS_DRIVER', 'velvet'),
    ],
];
