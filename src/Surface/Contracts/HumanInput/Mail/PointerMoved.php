<?php

namespace Surface\Contracts\HumanInput\Mail;

use Surface\Contracts\Graphics\ActorStage;

/** The pointer moved over the stage, or onto or off it: where it is, in the stage's pixels. */
final readonly class PointerMoved
{
    public function __construct(
        public ActorStage $stage,
        public float $x,
        public float $y,
        public bool $inside,
    ) {}
}
