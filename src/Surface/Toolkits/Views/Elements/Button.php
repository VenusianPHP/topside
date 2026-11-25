<?php

namespace Surface\Toolkits\Views\Elements;

use Closure;
use Surface\NutsAndBolts\Color;
use Surface\Contracts\Toolkits\UI\TKButton;
use Surface\Contracts\Toolkits\UI\ButtonRole;
use Surface\Contracts\Toolkits\UI\ButtonStyle;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Styling\TextAlignment;

/**
 * A push button.
 *
 *     <Button style="link" symbol="star" @click="discover">Discover</Button>
 */
class Button extends Element
{
    final public function __construct(protected readonly string $label) {}

    public static function make(string $label): static
    {
        return new static($label);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->button($name, $this->label);
    }

    public function kind(): string
    {
        return 'button';
    }

    /**
     * Run $handler when the user clicks the button. It is given the ButtonClicked mail; a
     * method that takes nothing ignores it: ->onClick($this->save(...)).
     */
    public function onClick(Closure $handler): static
    {
        return $this->on('click', $handler);
    }

    /**
     * default, borderless, or link (borderless, the link colour, a pointing hand).
     */
    public function style(ButtonStyle $style): static
    {
        return $this->set('style', $style);
    }

    public function font(FontSpec $font): static
    {
        return $this->set('font', $font);
    }

    /**
     * The label's colour; null is the toolkit's own.
     */
    public function color(?Color $color): static
    {
        return $this->set('color', $color);
    }

    /**
     * Where the label sits inside the button.
     */
    public function textAlign(TextAlignment $alignment): static
    {
        return $this->set('textAlign', $alignment);
    }

    /**
     * An image file before the label, absolute or under resources/.
     */
    public function icon(?string $src): static
    {
        return $this->set('icon', $src === '' ? null : $src);
    }

    /**
     * A symbol before the label, by the toolkit's own name for it (an SF Symbol on AppKit).
     */
    public function symbol(?string $name): static
    {
        return $this->set('symbol', $name === '' ? null : $name);
    }

    /**
     * default: Return clicks it. cancel: Esc clicks it.
     */
    public function role(?ButtonRole $role): static
    {
        return $this->set('role', $role);
    }

    /**
     * As a label's: a font cannot be taken off, so losing one builds the button again.
     */
    protected function shape(): array
    {
        return ['font' => $this->has('font')];
    }

    protected function tracked(): array
    {
        return ['label'];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'label' => fn (TKButton $p, string $v) => $p->setLabel($v),
            'style' => fn (TKButton $p, ButtonStyle $v) => $p->setStyle($v),
            'font' => fn (TKButton $p, FontSpec $v) => $p->setFont($v),
            'color' => fn (TKButton $p, ?Color $v) => $p->setTextColor($v),
            'textAlign' => fn (TKButton $p, TextAlignment $v) => $p->setAlignment($v),
            'icon' => fn (TKButton $p, ?string $v) => $p->setIcon(Image::path($v)),
            'symbol' => fn (TKButton $p, ?string $v) => $p->setSymbol($v),
            'role' => fn (TKButton $p, ?ButtonRole $v) => $p->setRole($v),
        ];
    }

    protected function defaults(): array
    {
        return parent::defaults() + [
            'style' => ButtonStyle::DEFAULT,
            'color' => null,
            'textAlign' => TextAlignment::CENTER,
            'icon' => null,
            'symbol' => null,
            'role' => null,
        ];
    }

    protected function valueOf(string $prop): mixed
    {
        return $prop === 'label' ? $this->label : parent::valueOf($prop);
    }

    protected function initial(string $prop): mixed
    {
        return $prop === 'label' ? $this->label : parent::initial($prop);
    }
}
