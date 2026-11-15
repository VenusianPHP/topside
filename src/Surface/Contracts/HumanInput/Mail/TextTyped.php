<?php

namespace Surface\Contracts\HumanInput\Mail;

use Surface\Contracts\Graphics\ActorStage;

/** Text the keys typed while the stage had the keyboard: what a key types, not the key. */
final readonly class TextTyped
{
    public function __construct(
        public ActorStage $stage,
        public string $text,
    ) {}
}
