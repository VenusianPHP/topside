<?php

namespace Surface\Contracts\HumanInput\Mail;

/** A pad went. A pad that comes back is a new connection with a new id. */
final readonly class PadDisconnected
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
