<?php

return [
    /*
        |----------------------------------------------------------------------
        | Human Input
        |----------------------------------------------------------------------
        |
        | Off unless turned on: an application with a canvas that reads the
        | keyboard, the pointer or game pads turns it on.
        |
        */
    'enabled' => (bool) env('HUMAN_INPUT', false),

    /*
        |----------------------------------------------------------------------
        | Input Backend
        |----------------------------------------------------------------------
        |
        | The library a canvas's keyboard and pointer come from, by the name
        | its package registered it with: 'appkit', 'raven', 'evdev', 'sdl3'.
        | Whether it can serve this application's toolkit, canvas engine and
        | OS is its own answer: the application refuses to boot when it
        | cannot.
        |
        */
    'backend' => env('HUMAN_INPUT_BACKEND'),

    /*
        |----------------------------------------------------------------------
        | Pad Source
        |----------------------------------------------------------------------
        |
        | The library the process's game pads come from: 'gamecontroller',
        | 'sdl3', 'evdev'. Null for no pads.
        |
        */
    'pads' => env('HUMAN_INPUT_PADS'),
];
