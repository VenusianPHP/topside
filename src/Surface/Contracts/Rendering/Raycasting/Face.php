<?php

namespace Surface\Contracts\Rendering\Raycasting;

/** The side of a wall cell a ray struck. x grows east and y grows south, so north is the side with the smaller y. */
enum Face: int
{
    /** The ray was travelling east. */
    case WEST = 0;

    /** The ray was travelling west. */
    case EAST = 1;

    /** The ray was travelling south. */
    case NORTH = 2;

    /** The ray was travelling north. */
    case SOUTH = 3;

    /** Whether a view darkens it by the scenery's shade: what tells one wall from the next at a corner. */
    public function isShaded(): bool
    {
        return $this === self::NORTH || $this === self::SOUTH;
    }
}
