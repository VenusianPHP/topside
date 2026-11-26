<?php

namespace Surface\Contracts\Toolkits\Styling;

/**
 * The colours an application's windows are drawn in: the system's choice, or dark or light
 * whatever the system chose. The names are CSS's and Qt's.
 */
enum ColorScheme: string
{
    case SYSTEM = 'system';
    case DARK = 'dark';
    case LIGHT = 'light';
}
