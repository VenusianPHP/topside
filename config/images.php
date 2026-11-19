<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Decoders
    |--------------------------------------------------------------------------
    |
    | The order a file is offered to the decoders packages register. 'auto'
    | means every registered decoder, in the order it was registered, at the
    | place 'auto' stands. The first that reads the format and writes the
    | frame's layout is the one that decodes it.
    |
    */
    'decoders' => ['auto'],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    |
    | Checked from the header, before a decoder writes a pixel. A side longer
    | than 'side', or a width times a height over 'pixels', is refused.
    |
    */
    'limits' => [
        'side' => (int) env('TOPSIDE_IMAGE_SIDE', 65535),
        'pixels' => (int) env('TOPSIDE_IMAGE_PIXELS', 1 << 26),
    ],
];
