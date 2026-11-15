<?php

namespace Surface\Contracts\HumanInput;

use Surface\Contracts\Graphics\ActorStage;

/**
 * Keyboard and mouse from one library, for the stages it can reach. Its package registers it with
 * HumanInputManager::extend(); config's human-input.backend names the one an application uses.
 *
 * A backend that cannot run here (its extension is missing, the OS is wrong) throws a
 * HumanInputException from its creator or constructor. One that runs but cannot reach what the
 * application is presented with answers false from serves().
 */
interface InputBackend
{
    /** The name it is registered by. */
    public function name(): string;

    /** Whether it reaches the stages of that toolkit, drawn with that engine, on that OS. */
    public function serves(InputContext $context): bool;

    /** Start listening. Idempotent. */
    public function connect(): static;

    public function disconnect(): void;

    public function connected(): bool;

    /** The stage's input: the one already open for it, or a new one. */
    public function open(ActorStage $stage): InputChannel;

    /** Apply what arrived since the last poll to the open channels. Never waits. */
    public function poll(): void;
}
