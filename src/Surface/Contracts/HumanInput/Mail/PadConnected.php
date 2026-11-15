<?php

namespace Surface\Contracts\HumanInput\Mail;

/** A pad came. Read it from a stage's input by its id: gameControllers() has it when $controller. */
final readonly class PadConnected
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $controller,
    ) {}
}
