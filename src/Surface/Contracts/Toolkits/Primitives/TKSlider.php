<?php

namespace Surface\Contracts\Toolkits\Primitives;

use Surface\Contracts\Toolkits\Primitives\TKPrimitive;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;

/**
 * A value dragged along a range. Posts ValueChanged.
 */
interface TKSlider extends TKPrimitive
{
    /**
     * @return float
     */
    public function value(): float;

    /**
     * Clamped into the range. Posts nothing: the app changed it, it knows.
     * @param float $value
     * @return $this
     */
    public function setValue(float $value): static;

    /**
     * @return float
     */
    public function min(): float;

    /**
     * @return float
     */
    public function max(): float;

    /**
     * The value is clamped into the new range.
     * @param float $min
     * @param float $max
     * @return $this
     * @throws ToolkitRuntimeException When min is not below max.
     */
    public function setRange(float $min, float $max): static;
}
