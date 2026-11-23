<?php

namespace Surface\Toolkits\Views\Elements;

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
    final public function __construct(
        protected readonly string $title,
        protected readonly int $spacing = 0,
        protected readonly int $padding = 0,
    ) {}

    public static function make(string $title, int $spacing = 0, int $padding = 0): static
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

    protected function shape(): array
    {
        return ['padding' => $this->padding];
    }

    protected function made(): array
    {
        return ['title' => $this->title, 'spacing' => $this->spacing];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'title' => fn (TKColumn $p, string $v) => $p->parent()->setTitle($p->name(), $v),
            'spacing' => fn (TKColumn $p, int $v) => $p->setSpacing($v),
        ];
    }
}
