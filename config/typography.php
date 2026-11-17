<?php

$home = (string) getenv('HOME');

return [
    /*
        |----------------------------------------------------------------------
        | Drivers
        |----------------------------------------------------------------------
        |
        | What finds fonts, what reads them and what shapes text with them.
        | Packages register these; 'auto' is what the package that registered
        | it chooses.
        |
        */
    'catalog' => [
        'default' => env('TOPSIDE_FONT_CATALOG', 'auto'),
    ],

    'face' => [
        'default' => env('TOPSIDE_FONT_FACE', 'auto'),
    ],

    'shaper' => [
        'default' => env('TOPSIDE_FONT_SHAPER', 'auto'),
    ],

    /*
        |----------------------------------------------------------------------
        | Font Files
        |----------------------------------------------------------------------
        |
        | Where a catalog with no list of its own looks for fonts, and files it
        | adds to what it finds there.
        |
        */
    'directories' => PHP_OS_FAMILY === 'Darwin'
        ? ['/System/Library/Fonts', '/Library/Fonts', "{$home}/Library/Fonts"]
        : ['/usr/share/fonts', '/usr/local/share/fonts', "{$home}/.local/share/fonts", "{$home}/.fonts"],

    'files' => [],

    // Where a catalog keeps what it found between runs; null leaves it to the catalog's driver.
    'cache' => env('TOPSIDE_FONT_CACHE'),

    /*
        |----------------------------------------------------------------------
        | Generic Families
        |----------------------------------------------------------------------
        |
        | The families a generic name stands for, first found first used, for
        | a catalog with no aliases of its own. 'sans-serif' is also what a
        | request falls back on when none of its families is found.
        |
        */
    'families' => [
        'sans-serif' => ['Helvetica Neue', 'Helvetica', 'Arial', 'DejaVu Sans', 'Liberation Sans', 'Noto Sans'],
        'serif' => ['Times', 'Times New Roman', 'DejaVu Serif', 'Liberation Serif', 'Noto Serif'],
        'monospace' => ['Menlo', 'Monaco', 'Courier New', 'DejaVu Sans Mono', 'Liberation Mono', 'Noto Sans Mono'],
    ],

    /*
        |----------------------------------------------------------------------
        | Fallback Families
        |----------------------------------------------------------------------
        |
        | Tried, in order, for a character the requested families lack, before
        | every other family the catalog has.
        |
        */
    'fallbacks' => [
        'Apple Color Emoji', 'Noto Color Emoji',
        'Hiragino Sans', 'PingFang SC', 'Apple SD Gothic Neo', 'Noto Sans CJK SC',
        'Apple Symbols', 'Noto Sans Symbols', 'Noto Sans Symbols 2', 'DejaVu Sans',
    ],

    /*
        |----------------------------------------------------------------------
        | Fonts
        |----------------------------------------------------------------------
        |
        | The fonts the application names. An entry asks by 'families',
        | 'weight' (1..1000) and 'slant' (upright, italic, oblique), or names
        | a 'file' (and its 'index' in a collection); every entry has a 'size'.
        |
        */
    'default' => env('TOPSIDE_FONT', 'body'),

    'fonts' => [
        'body' => ['families' => ['sans-serif'], 'size' => 14],
        'mono' => ['families' => ['monospace'], 'size' => 13],
    ],
];
