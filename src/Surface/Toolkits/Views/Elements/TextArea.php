<?php

namespace Surface\Toolkits\Views\Elements;

use Closure;
use Surface\NutsAndBolts\Color;
use Surface\Contracts\Toolkits\UI\TKTextArea;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Surface\Contracts\Toolkits\Mail\Views\TextChanged;

/**
 * Text of many lines. Give it the text it shows, and a handler that keeps it. It takes the
 * room there is unless told fill="false".
 *
 *     <TextArea :value="$notes" @change="fn ($text) => $this->notes = $text" />
 */
class TextArea extends Element
{
    final public function __construct(
        protected readonly string $value = '',
    ) {
        $this->fill();
    }

    public static function make(string $value = ''): static
    {
        return new static($value);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->textArea($name, $this->value);
    }

    public function kind(): string
    {
        return 'textArea';
    }

    /**
     * Run $handler as the user types, with the text and the mail.
     *
     * @param Closure(string, TextChanged): mixed $handler
     */
    public function onChange(Closure $handler): static
    {
        // What it holds now, as a TextInput's.
        return $this->onLive('input', static fn (TextChanged $mail, TKTextArea $area) => $handler($area->value(), $mail));
    }

    /**
     * Read-only text still selects and copies.
     */
    public function readOnly(bool $readOnly = true): static
    {
        return $this->set('readOnly', $readOnly);
    }

    /**
     * Wraps at its width unless wrap="false", which scrolls sideways.
     */
    public function wrap(bool $wrap = true): static
    {
        return $this->set('wrap', $wrap);
    }

    public function tabWidth(int $columns): static
    {
        return $this->set('tabWidth', $columns);
    }

    public function textAlign(TextAlignment $alignment): static
    {
        return $this->set('textAlign', $alignment);
    }

    public function font(FontSpec $font): static
    {
        return $this->set('font', $font);
    }

    public function color(?Color $color): static
    {
        return $this->set('color', $color);
    }

    protected function shape(): array
    {
        return ['font' => $this->has('font')];
    }

    protected function made(): array
    {
        return ['value' => $this->value];
    }

    protected function valueOf(string $prop): mixed
    {
        return $prop === 'value' ? $this->value : parent::valueOf($prop);
    }

    protected function defaults(): array
    {
        return parent::defaults() + ['readOnly' => false, 'wrap' => true, 'tabWidth' => 8, 'textAlign' => TextAlignment::LEFT, 'color' => null];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'value' => function (TKTextArea $p, string $v): void {
                // Writing what it holds would move the cursor. It holds "\n" line ends.
                if ($p->value() !== str_replace(["\r\n", "\r"], "\n", $v)) {
                    $p->setValue($v);
                }
            },
            'readOnly' => fn (TKTextArea $p, bool $v) => $p->setReadOnly($v),
            'wrap' => fn (TKTextArea $p, bool $v) => $p->setWrap($v),
            'tabWidth' => fn (TKTextArea $p, int $v) => $p->setTabWidth($v),
            'textAlign' => fn (TKTextArea $p, TextAlignment $v) => $p->setAlignment($v),
            'font' => fn (TKTextArea $p, FontSpec $v) => $p->setFont($v),
            'color' => fn (TKTextArea $p, ?Color $v) => $p->setTextColor($v),
        ];
    }
}
