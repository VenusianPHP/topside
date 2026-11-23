<?php

namespace Surface\Contracts\Toolkits\Menus;

/**
 * What a toolkit builds for a node of a bar.
 */
enum BarNodeKind
{
    case MENU;
    case ITEM;
    case TOGGLE;

    /** One node, shown as one native item per option. */
    case CHOICE;

    case SEPARATOR;
    case STANDARD;
}
