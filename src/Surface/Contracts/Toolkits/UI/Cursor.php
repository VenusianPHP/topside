<?php

namespace Surface\Contracts\Toolkits\UI;

/**
 * The pointer's shape while it is over a primitive. Named as CSS names them. A toolkit with
 * no such shape of its own keeps its own pointer: none is drawn in its place.
 */
enum Cursor: string
{
    case ARROW = 'arrow';
    /** A pointing hand: something to click. */
    case POINTER = 'pointer';
    /** The text beam. */
    case TEXT = 'text';
    case CROSSHAIR = 'crosshair';
    case MOVE = 'move';
    case NOT_ALLOWED = 'not-allowed';
    case GRAB = 'grab';
    case GRABBING = 'grabbing';
    case RESIZE_HORIZONTAL = 'resize-horizontal';
    case RESIZE_VERTICAL = 'resize-vertical';
    /** From top left to bottom right. */
    case RESIZE_NWSE = 'resize-nwse';
    /** From top right to bottom left. */
    case RESIZE_NESW = 'resize-nesw';
}
