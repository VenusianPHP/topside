<?php

namespace Surface\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\UI\TKSpinner as PrimitiveContract;

/**
 * Something is happening. It spins from the start unless built otherwise.
 */
abstract class TKSpinner extends TKPrimitive implements PrimitiveContract
{
    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
        private bool $spinning = true,
    ) {
        parent::__construct($name, $window, $parent, $placement);
    }

    public function start(): static
    {
        return $this->spin(true);
    }

    public function stop(): static
    {
        return $this->spin(false);
    }

    public function isSpinning(): bool
    {
        return $this->spinning;
    }

    private function spin(bool $spinning): static
    {
        $this->guardLive();

        if ($spinning !== $this->spinning) {
            $this->spinning = $spinning;
            $this->applySpinning($spinning);
        }

        return $this;
    }

    abstract protected function applySpinning(bool $spinning): void;
}
