<?php

namespace Surface\Contracts\Graphics;

use Closure;
use Surface\Contracts\Graphics\ActorStage;

/**
 * A stage that knows when the display it is on is due a frame. A framebuffer presented on one is
 * shown then, not at once: however often it is drawn, it is shown once a refresh, and what is
 * shown is the latest frame. Frames the display could never show cost nothing to present.
 */
interface PacedStage extends ActorStage
{
    /**
     * Calls $show once, the next time the display is due a frame. Asked again before then, the
     * new $show takes the place of the old one: a stage shows one framebuffer.
     */
    public function whenDue(Closure $show): void;
}
