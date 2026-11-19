<?php

namespace Surface\Contracts\Rendering\Recording\Enums;

/**
 * Why a recorder refused a shape. The values are ext-comrec's exception codes.
 */
enum RecordingError: int
{
    /** A shape with no frame open. */
    case NOT_RECORDING = 1;

    /** An argument is not a finite number. */
    case NOT_FINITE = 2;

    /** A coordinate, a radius or a stroke is past 16777216 once transformed. */
    case PAST_LIMIT = 3;

    /** Radii are not a list of four numbers. */
    case BAD_RADII = 4;

    /** shadow() under a transform that is not upright and even. */
    case UNEVEN = 5;

    /** concat() would make a transform component that is not finite. */
    case BAD_MATRIX = 6;
}
