<?php

namespace Surface\Toolkits\Views\Elements;

use Surface\Contracts\Toolkits\UI\TKLink;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Styling\TextAlignment;

/**
 * Text that opens a URL. The toolkit opens it: there is no handler to give.
 *
 *     Link::make('Documentation', 'https://...')
 */
class Link extends Element
{
    final public function __construct(
        protected readonly string $text,
        protected readonly string $url,
    ) {}

    public static function make(string $text, string $url): static
    {
        return new static($text, $url);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->link($name, $this->text, $this->url);
    }

    public function kind(): string
    {
        return 'link';
    }

    public function textAlign(TextAlignment $alignment): static
    {
        return $this->set('textAlign', $alignment);
    }

    public function font(FontSpec $font): static
    {
        return $this->set('font', $font);
    }

    /**
     * As a label's: a font cannot be taken off, so losing one builds the link again.
     */
    protected function shape(): array
    {
        return ['font' => $this->has('font')];
    }

    protected function tracked(): array
    {
        return ['text', 'url'];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'text' => fn (TKLink $p, string $v) => $p->setText($v),
            'url' => fn (TKLink $p, string $v) => $p->setUrl($v),
            'textAlign' => fn (TKLink $p, TextAlignment $v) => $p->setAlignment($v),
            'font' => fn (TKLink $p, FontSpec $v) => $p->setFont($v),
        ];
    }

    protected function defaults(): array
    {
        return parent::defaults() + ['textAlign' => TextAlignment::LEFT];
    }

    protected function valueOf(string $prop): mixed
    {
        return match ($prop) {
            'text' => $this->text,
            'url' => $this->url,
            default => parent::valueOf($prop),
        };
    }

    protected function initial(string $prop): mixed
    {
        return in_array($prop, ['text', 'url'], true) ? $this->valueOf($prop) : parent::initial($prop);
    }
}
