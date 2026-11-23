<?php

namespace Surface\Toolkits\Views\Elements;

use Closure;
use Surface\Contracts\Toolkits\UI\TKSlider;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Mail\Views\ValueChanged;

/**
 * A value between two others, 0 to 1 unless told. Give it a handler that keeps it:
 *
 *     <Slider :value="$volume" max="100" @change="fn ($v) => $this->volume = $v" />
 */
class Slider extends Element
{
    final public function __construct(
        protected readonly float $value = 0.0,
        protected readonly float $min = 0.0,
        protected readonly float $max = 1.0,
    ) {}

    /**
     * @param float $value kept inside min and max
     */
    public static function make(float $value = 0.0, float $min = 0.0, float $max = 1.0): static
    {
        return new static($value, $min, $max);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->slider($name, $this->min, $this->max, $this->value);
    }

    public function kind(): string
    {
        return 'slider';
    }

    /**
     * Run $handler as the user moves it, with the value and the mail.
     *
     * @param Closure(float, ValueChanged): mixed $handler
     */
    public function onChange(Closure $handler): static
    {
        // The value held now: an older one given back would move the knob from under the finger.
        return $this->onLive('change', static fn (ValueChanged $mail, TKSlider $slider) => $handler($slider->value(), $mail));
    }

    /**
     * The range and the value are one setting: a new range clamps the value, so the value is
     * set after it, against what the primitive holds.
     */
    protected function made(): array
    {
        return ['amount' => [$this->min, $this->max, $this->value]];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'amount' => function (TKSlider $p, array $v): void {
                [$min, $max, $value] = $v;
                if ($p->min() !== $min || $p->max() !== $max) {
                    $p->setRange($min, $max);
                }
                if ($p->value() !== $value) {
                    $p->setValue($value);
                }
            },
        ];
    }
}
