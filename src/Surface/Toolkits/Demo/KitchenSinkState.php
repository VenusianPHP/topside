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
}
