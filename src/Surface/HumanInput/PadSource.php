<?php

namespace Surface\HumanInput;

use Surface\Contracts\HumanInput\PadSource as PadSourceContract;
use Surface\HumanInput\Devices\GamePad;

/**
 * What every pad source keeps the same way: the pads connected now, by id. A source adds a pad
 * when its library reports one, removes it when it goes, and reports its buttons and axes to it.
 */
abstract class PadSource implements PadSourceContract
{
    /** @var array<string, GamePad> */
    protected array $pads = [];

    public function pads(): array
    {
        return $this->pads;
    }

    protected function add(GamePad $pad): void
    {
        $this->pads[$pad->id] = $pad;
    }

    /** The pad went: its held buttons go up first, so a stage that reads it last sees the releases. */
    protected function remove(string $id): void
    {
        ($this->pads[$id] ?? null)?->releaseAll();
        unset($this->pads[$id]);
    }
}
