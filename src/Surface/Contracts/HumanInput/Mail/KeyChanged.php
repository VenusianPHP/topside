<?php

namespace Surface\Contracts\HumanInput\Mail;

use Surface\Contracts\Graphics\ActorStage;
use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\HumanInput\Modifiers;

/** A key went down or up while the stage had the keyboard. A held key's repeats are not sent. */
final readonly class KeyChanged
{
    public function __construct(
        public ActorStage $stage,
        public Key $key,
        public bool $down,
        public Modifiers $modifiers,
    ) {}
}
