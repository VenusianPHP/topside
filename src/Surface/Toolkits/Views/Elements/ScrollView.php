<?php

namespace Surface\Toolkits\Views\Elements;

use Closure;
use Surface\Contracts\Toolkits\UI\Insets;
use Surface\Contracts\Toolkits\Mail\Views\ViewScrolled;
use Surface\Contracts\Toolkits\Styling\ScrollPosition;
use Surface\Contracts\Toolkits\UI\TKScrollView;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;

/**
 * Whatever is inside, one under another, scrolled when there is more than fits. It takes the
 * room there is unless told fill="false".
 *
 *     <ScrollView spacing="8" padding="12">
 *         @foreach ($lines as $i => $line) <Label :key="$i">{{ $line }}</Label> @endforeach
 *     </ScrollView>
 */
class ScrollView extends Container
{
    protected readonly Insets $padding;

    final public function __construct(
        protected readonly int $spacing = 0,
        Insets|int|string|array $padding = 0,
        protected readonly bool $horizontal = false,
        protected readonly bool $vertical = true,
    ) {
        $this->padding = self::insetsOf($padding, 'padding');
        $this->fill();
    }

    /**
     * @param int $spacing between one child and the next
     * @param Insets|int|string|list<int> $padding around them all, scrolled with them, as CSS writes it
     * @param bool $horizontal across as well
     * @param bool $vertical up and down
     */
    public static function make(int $spacing = 0, Insets|int|string|array $padding = 0, bool $horizontal = false, bool $vertical = true): static
    {
        return new static($spacing, $padding, $horizontal, $vertical);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->scrollView($name)->setScrollbars($this->horizontal, $this->vertical);
    }

    public function kind(): string
    {
        return 'scrollView';
    }

    /**
     * Run $handler when the user scrolls, with where the view is and the ViewScrolled mail:
     * $position->y + $position->height is 1.0 at the bottom.
     *
     *     <ScrollView @scroll="fn ($at) => $at->y + $at->height > 0.9 ? $this->more() : null">
     *
     * @param Closure(ScrollPosition, ViewScrolled): mixed $handler
     */
    public function onScroll(Closure $handler): static
    {
        return $this->set('watchScroll', true)->on('scroll', static fn (ViewScrolled $mail) => $handler($mail->position, $mail));
    }

    /**
     * A scroll view scrolls one container: the column the children are put in.
     */
    public function children(): array
    {
        return [Column::make($this->spacing, $this->padding)->key("\0content")->with(...$this->children)];
    }

    protected function made(): array
    {
        return ['scroll' => [$this->horizontal, $this->vertical]];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'scroll' => fn (TKScrollView $p, array $v) => $p->setScrollbars(...$v),
            'watchScroll' => fn (TKScrollView $p, bool $v) => $p->watchScroll($v),
        ];
    }

    protected function defaults(): array
    {
        return parent::defaults() + ['watchScroll' => false];
    }
}
