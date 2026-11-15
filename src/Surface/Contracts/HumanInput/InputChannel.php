<?php

namespace Surface\Contracts\HumanInput;

use Surface\Contracts\Graphics\ActorStage;
use Surface\Contracts\HumanInput\Devices\GameController;
use Surface\Contracts\HumanInput\Devices\GamePad;
use Surface\Contracts\HumanInput\Devices\Keyboard;
use Surface\Contracts\HumanInput\Devices\Mouse;

/**
 * One stage's input: its keyboard and mouse, in the stage's pixels, and the process's pads as
 * this stage has seen them. Each change is also sent as mail.
 *
 * Edges are this channel's own: a press reads as pressed until settle(), on this channel only.
 */
interface InputChannel
{
    public function stage(): ActorStage;

    public function keyboard(): Keyboard;

    public function mouse(): Mouse;

    /** @return array<string, GamePad> every connected pad, game controllers included, by id */
    public function gamePads(): array;

    /** @return array<string, GameController> the pads with sticks and triggers, by id */
    public function gameControllers(): array;

    /** The frame that read this input is over: clear the edges, text, motion and wheel. */
    public function settle(): void;

    /** Stop taking input for the stage. Reads after say so. */
    public function close(): void;

    public function closed(): bool;
}
