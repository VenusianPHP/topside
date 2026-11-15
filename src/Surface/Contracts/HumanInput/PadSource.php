<?php

namespace Surface\Contracts\HumanInput;

use Surface\Contracts\HumanInput\Devices\GamePad;

/**
 * The process's game pads, from one library, for every stage. One runs per process: its package
 * registers it with HumanInputManager::extendPads(); config's human-input.pads names it. It
 * refuses as a backend does, by throwing from its creator or constructor.
 */
interface PadSource
{
    public function name(): string;

    /** Start listening. Idempotent. */
    public function connect(): static;

    public function disconnect(): void;

    public function connected(): bool;

    /** Apply what arrived since the last poll. Never waits. */
    public function poll(): void;

    /** @return array<string, GamePad> connected pads, game controllers included, by id */
    public function pads(): array;
}
