<?php

namespace Surface\Contracts\Graphics;

use Closure;

/**
 * A stage that knows when the display it is on is due a frame. Asked to show something then, it shows the latest
 * thing asked once a refresh: what the display could never show costs nothing.
 */
interface PacedStage extends ActorStage
{
    /** Calls $show once, the next time the display is due a frame. Asked again before then, the new $show replaces the old. */
    public function whenDue(Closure $show): void;
}
