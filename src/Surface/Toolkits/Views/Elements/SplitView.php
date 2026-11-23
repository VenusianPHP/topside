<?php

namespace Surface\Toolkits\Views\Elements;

use Closure;
use Surface\Toolkits\Views\ViewException;
use Surface\Contracts\Toolkits\UI\TKSplitView;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Mail\Views\SplitMoved;

/**
 * Two panes with a divider the user drags: side by side, or one over the other when stacked.
 * Three are a SplitView inside a pane. It takes the room there is unless told fill="false".
 *
 *     <SplitView position="0.3">
 *         <Column>...</Column>
 *         <Column>...</Column>
 *     </SplitView>
 */
class SplitView extends Container
{
    final public function __construct(
        protected readonly float $position = 0.5,
        protected readonly bool $stacked = false,
    ) {
        $this->fill();
    }

    /**
     * @param float $position the first pane's share, 0 to 1
     * @param bool $stacked one over the other
     */
    public static function make(float $position = 0.5, bool $stacked = false): static
    {
        return new static($position, $stacked);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->split($name, ! $this->stacked)->setPositions([$this->position]);
    }

    public function kind(): string
    {
        return 'splitView';
    }

    /**
     * @throws ViewException For more than two.
     */
    public function children(): array
    {
        if (count(array_filter($this->children)) > 2) {
            throw new ViewException('A SplitView has two panes: put another SplitView inside one for a third.');
        }

        return $this->children;
    }

    /**
     * Run $handler when the user has moved the divider, with its place and the mail.
     *
     * @param Closure(float, SplitMoved): mixed $handler
     */
    public function onChange(Closure $handler): static
    {
        // Where it is now, as a Slider's value.
        return $this->onLive('change', static fn (SplitMoved $mail, TKSplitView $split) => $handler($split->positions()[0] ?? $mail->positions[0], $mail));
    }

    protected function shape(): array
    {
        return ['stacked' => $this->stacked];
    }

    protected function made(): array
    {
        return ['position' => $this->position];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'position' => fn (TKSplitView $p, float $v) => $p->setPositions([$v]),
        ];
    }
}
