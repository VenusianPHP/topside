<?php

namespace Surface\Toolkits\UI;

use Surface\Toolkits\ToolkitException;
use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\UI\TKProgressBar as PrimitiveContract;

/**
 * How far along something is, 0 to 1; null while that is not known.
 */
abstract class TKProgressBar extends TKPrimitive implements PrimitiveContract
{
    private ?float $fraction;

    /**
     * @throws ToolkitException When the fraction is not 0 to 1.
     */
    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
        ?float $fraction = null,
    ) {
        $this->fraction = self::guardFraction($fraction, $name);

        parent::__construct($name, $window, $parent, $placement);
    }

    public function fraction(): ?float
    {
        return $this->fraction;
    }

    /**
     * @throws ToolkitException When removed, or the fraction is not 0 to 1.
     */
    public function setFraction(?float $fraction): static
    {
        $this->guardLive();

        $this->fraction = self::guardFraction($fraction, $this->path());
        $this->applyFraction($this->fraction);

        return $this;
    }

    /**
     * @throws ToolkitException When not 0 to 1 (NAN is not).
     */
    private static function guardFraction(?float $fraction, string $where): ?float
    {
        if (! is_null($fraction) && ! ($fraction >= 0.0 && $fraction <= 1.0)) {
            throw new ToolkitException("Progress is 0 to 1, or null when not known, got {$fraction} for '{$where}'.");
        }

        return $fraction;
    }

    /**
     * @param float|null $fraction null: show that it is not known
     */
    abstract protected function applyFraction(?float $fraction): void;
}
