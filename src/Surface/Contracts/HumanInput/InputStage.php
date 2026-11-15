<?php

namespace Surface\Contracts\HumanInput;

use Surface\Contracts\Graphics\ActorStage;

/**
 * A stage that can have input. Its input's edges (a press, a release, typed text, motion, the
 * wheel) last until the frame that read them is presented: the framebuffer presented on it calls
 * settleInput() each time it is presented.
 */
interface InputStage extends ActorStage
{
    /** The stage's input. Opened the first time it is asked for. */
    public function input(): InputChannel;

    /** A frame was presented: clears the edges its drawing read. A stage whose input was never opened does nothing. */
    public function settleInput(): void;
}
