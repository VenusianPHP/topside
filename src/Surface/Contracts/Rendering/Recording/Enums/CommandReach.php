<?php

namespace Surface\Contracts\Rendering\Recording\Enums;

/**
 * How a recorder finds where a command made elsewhere draws. The values are ext-comrec's.
 */
enum CommandReach: int
{
    /** The whole surface. */
    case WHOLE = 1;

    /** Around the points of command[1], a list of lists of [x, y], $grow out, inside the clip. */
    case CONTOURS = 2;

    /** Around the points of command[1], a list of [x, y], $grow out, inside the clip. */
    case POINTS = 3;

    /** The box the caller gives in $pieces. */
    case PIECES = 5;
}
