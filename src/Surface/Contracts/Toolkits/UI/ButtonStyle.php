<?php

namespace Surface\Contracts\Toolkits\UI;

/**
 * How a push button draws:
 *
 * DEFAULT    the toolkit's own button, with its frame.
 * BORDERLESS no frame and no fill of its own: the label (and icon) alone, still a button.
 * LINK       borderless, its label in the toolkit's link colour, a pointing hand over it.
 */
enum ButtonStyle: string
{
    case DEFAULT = 'default';
    case BORDERLESS = 'borderless';
    case LINK = 'link';
}
