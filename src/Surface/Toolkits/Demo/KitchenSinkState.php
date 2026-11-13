<?php

namespace Surface\Toolkits\Demo;

/**
 * What the kitchen sink page remembers while the application runs. The view's handlers change
 * it and its labels read it. An application keeps state like this in its own services.
 */
class KitchenSinkState
{
    /** How many times the counter button was clicked. */
    public int $clicks = 0;

    /** The name last submitted in the input. */
    public string $name = '';

    /** The fruit last submitted in the search field. */
    public string $fruit = '';

    /** Whether the reveal section is open. */
    public bool $open = false;

    /** How many overlays have been opened: each gets its own name. */
    public int $overlays = 0;
}
