<?php

namespace Surface\Contracts\Toolkits\UI;

use RuntimeException;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

/**
 * Children on top of each other: the first at the back, each later one drawn above the one
 * before. Each child is placed inside the overlay (less its padding) by its own align() and
 * margin; one that fills takes the whole area.
 *
 * A child that fills both ways is sized by the overlay and never sizes it: a large picture
 * covers the overlay without making it, or the window, the picture's size. The overlay's
 * natural size is the largest of its other children. Children reorder with
 * moveBefore()/moveAfter()/moveTo(), which changes which is drawn above which.
 *
 * A child on top takes the mouse wherever it covers, even where it shows nothing.
 */
interface TKOverlay extends TKPrimitiveGroup
{
    /**
     * Space between the edge and the children.
     * @return Insets
     */
    public function padding(): Insets;

    /**
     * @param Insets|int|string|list<int> $padding one to four values, as CSS takes them
     * @return $this
     * @throws RuntimeException When a side is negative.
     */
    public function setPadding(Insets|int|string|array $padding): static;
}
