<?php

namespace Surface\Contracts\HumanInput\Mail;

use Surface\Contracts\Graphics\ActorStage;
use Surface\Contracts\HumanInput\ScrollPhase;

/** The stage was scrolled: in notches, or in its pixels when $precise. See Devices\Mouse for the directions. */
final readonly class PointerScrolled
{
    public function __construct(
        public ActorStage $stage,
        public float $dx,
        public float $dy,
        public bool $precise,
        public bool $inverted,
        public ScrollPhase $phase,
    ) {}
}
