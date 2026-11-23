<?php

namespace Surface\Toolkits\Views\Elements;

use Surface\NutsAndBolts\Color;
use Surface\Contracts\Toolkits\UI\TKLabel;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Styling\TextAlignment;

/**
 * Text the user reads.
 */
class Label extends Element
{
    final public function __construct(protected readonly string $text) {}

    public static function make(string $text): static
    {
        return new static($text);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->label($name, $this->text);
    }

    public function kind(): string
    {
        return 'label';
    }

    /**
     * True wraps at the width it is given instead of growing.
     */
    public function wrap(bool $wrap = true): static
    {
        return $this->set('wrap', $wrap);
    }

    /**
     * How lines sit inside the label's own width; align() places the label itself.
     */
    public function textAlign(TextAlignment $alignment): static
    {
        return $this->set('textAlign', $alignment);
    }

    public function font(FontSpec $font): static
    {
        return $this->set('font', $font);
    }

    /**
     * Null is the toolkit's own text colour.
     */
    public function color(?Color $color): static
    {
        return $this->set('color', $color);
    }

    /**
     * A font cannot be taken off a label (there is no "the toolkit's font" to set back), so a
     * label that had one and has none is built again.
     */
    protected function shape(): array
    {
        return ['font' => $this->has('font')];
    }

    protected function tracked(): array
    {
        return ['text'];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'text' => fn (TKLabel $p, string $v) => $p->setText($v),
            'wrap' => fn (TKLabel $p, bool $v) => $p->setWrap($v),
            'textAlign' => fn (TKLabel $p, TextAlignment $v) => $p->setAlignment($v),
            'font' => fn (TKLabel $p, FontSpec $v) => $p->setFont($v),
            'color' => fn (TKLabel $p, ?Color $v) => $p->setTextColor($v),
        ];
    }

    protected function defaults(): array
    {
        return parent::defaults() + [
            'wrap' => false,
            'textAlign' => TextAlignment::LEFT,
            'color' => null,
        ];
    }

    protected function valueOf(string $prop): mixed
    {
        return $prop === 'text' ? $this->text : parent::valueOf($prop);
    }

    protected function initial(string $prop): mixed
    {
        return $prop === 'text' ? $this->text : parent::initial($prop);
    }
}
