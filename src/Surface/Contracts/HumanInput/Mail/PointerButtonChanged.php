<?php

namespace Surface\Contracts\HumanInput\Mail;

use Surface\Contracts\Graphics\ActorStage;
use Surface\Contracts\HumanInput\Modifiers;
use Surface\Contracts\HumanInput\MouseButton;

/** A pointer button went down or up on the stage, at a point in its pixels. */
final readonly class PointerButtonChanged
{
    public function __construct(
        public ActorStage $stage,
        public MouseButton $button,
        public bool $down,
        public float $x,
        public float $y,
        public Modifiers $modifiers,
    ) {}
}
