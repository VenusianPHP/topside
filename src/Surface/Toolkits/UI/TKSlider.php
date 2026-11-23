<?php

namespace Surface\Toolkits\UI;

use Surface\Toolkits\ToolkitException;
use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Toolkits\UI\Concerns\IgnoresItsOwnWrites;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\Mail\Views\ValueChanged;
use Surface\Contracts\Toolkits\UI\TKSlider as PrimitiveContract;

/**
 * A value between two others, changed by dragging. The value is always inside the range.
 *
 * The toolkit's slider calls slid() from the native's own signal. Toolkits round what is
 * written to them and signal it, so every write here is inside write().
 */
abstract class TKSlider extends TKPrimitive implements PrimitiveContract
{
    use IgnoresItsOwnWrites;

    private float $min;

    private float $max;

    private float $value;

    /**
     * @throws ToolkitException When the range or value is not finite, or min is not below max.
     */
    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
        float $min = 0.0,
        float $max = 1.0,
        float $value = 0.0,
    ) {
        self::guardRange($min, $max, $name);
        self::guardValue($value, $name);
        [$this->min, $this->max] = [$min, $max];
        $this->value = $this->clamp($value);

        parent::__construct($name, $window, $parent, $placement);
    }

    public function value(): float
    {
        return $this->value;
    }

    /**
     * Clamped into the range. Posts nothing: the app changed it, it knows.
     *
     * @throws ToolkitException When removed, or the value is not finite.
     */
    public function setValue(float $value): static
    {
        $this->guardLive();
        self::guardValue($value, $this->path());

        $this->value = $this->clamp($value);
        $this->write(fn () => $this->applyValue($this->value));

        return $this;
    }

    public function min(): float
    {
        return $this->min;
    }

    public function max(): float
    {
        return $this->max;
    }

    /**
     * The value is clamped into the new range, and posts nothing.
     *
     * @throws ToolkitException When removed, the range is not finite, or min is not below max.
     */
    public function setRange(float $min, float $max): static
    {
        $this->guardLive();
        self::guardRange($min, $max, $this->path());

        [$this->min, $this->max] = [$min, $max];
        $this->value = $this->clamp($this->value);
        $this->write(fn () => $this->applyRange($min, $max, $this->value));

        return $this;
    }

    /**
     * The user moved it to $value: record it and post ValueChanged, the latest per pump. Nothing when it is the
     * value already held, topside's own write, or from a slider that takes no input.
     */
    protected function slid(float $value): void
    {
        if (! is_finite($value) || ! $this->hearsUser()) {
            return;
        }

        $value = $this->clamp($value);
        if ($value === $this->value) {
            return;
        }

        $this->value = $value;
        $this->window()->postLatest('view.value-changed.'.$this->uuid(), new ValueChanged($this->window()->name(), $this->path(), $this->uuid(), $value));
    }

    private function clamp(float $value): float
    {
        return max($this->min, min($this->max, $value));
    }

    /**
     * @throws ToolkitException
     */
    private static function guardRange(float $min, float $max, string $where): void
    {
        if (! is_finite($min) || ! is_finite($max) || $min >= $max) {
            throw new ToolkitException("A slider runs from a number to a larger one, got {$min} to {$max} for '{$where}'.");
        }
    }

    /**
     * @throws ToolkitException
     */
    private static function guardValue(float $value, string $where): void
    {
        if (! is_finite($value)) {
            throw new ToolkitException("A slider's value is a finite number, got {$value} for '{$where}'.");
        }
    }

    abstract protected function applyValue(float $value): void;

    /**
     * @param float $value the value to show in the new range, already inside it
     */
    abstract protected function applyRange(float $min, float $max, float $value): void;
}
