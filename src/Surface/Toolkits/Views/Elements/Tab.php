<?php

namespace Surface\Toolkits\Views\Elements;

use Surface\Contracts\Toolkits\UI\Insets;
use Surface\Toolkits\Views\ViewException;
use Surface\Contracts\Toolkits\UI\TKColumn;
use Surface\Contracts\Toolkits\UI\TKTabView;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

/**
 * One page of a TabView: a column with a title on its tab.
 */
class Tab extends Container
{
    protected readonly Insets $padding;

    final public function __construct(
        protected readonly string $title,
        protected readonly int $spacing = 0,
        Insets|int|string|array $padding = 0,
    ) {
        $this->padding = self::insetsOf($padding, 'padding');
    }

    /**
     * @param Insets|int|string|list<int> $padding round the page's children, as CSS writes it
     */
    public static function make(string $title, int $spacing = 0, Insets|int|string|array $padding = 0): static
    {
        return new static($title, $spacing, $padding);
    }

    /**
     * @throws ViewException Outside a tab view.
     */
    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        if (! $parent instanceof TKTabView) {
            throw new ViewException("The Tab '{$this->title}' is in '{$parent->path()}', which is no TabView: a Tab goes straight inside one.");
        }

        return $parent->addPage($name, $this->title, spacing: $this->spacing, padding: $this->padding);
    }

    public function kind(): string
    {
        return 'tab';
    }

    protected function made(): array
    {
        return ['title' => $this->title, 'spacing' => $this->spacing, 'padding' => $this->padding];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'title' => fn (TKColumn $p, string $v) => $p->parent()->setTitle($p->name(), $v),
            'spacing' => fn (TKColumn $p, int $v) => $p->setSpacing($v),
            'padding' => fn (TKColumn $p, Insets $v) => $p->setPadding($v),
        ];
    }
}
