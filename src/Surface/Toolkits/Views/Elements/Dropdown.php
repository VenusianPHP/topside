<?php

namespace Surface\Toolkits\Views\Elements;

use Closure;
use Surface\Contracts\Toolkits\UI\TKDropdown;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Mail\Views\SelectionChanged;

/**
 * One choice from a list. Give it the selection it shows, and a handler that keeps it:
 *
 *     Dropdown::make(['Small', 'Medium', 'Large'], selected: $this->size)
 *         ->onChange(fn (int $index) => $this->size = $index)
 */
class Dropdown extends Element
{
    /**
     * @param list<string> $options
     */
    final public function __construct(
        protected readonly array $options,
        protected readonly int $selected = 0,
    ) {}

    /**
     * @param list<string> $options
     * @param int $selected ignored when there are no options
     */
    public static function make(array $options, int $selected = 0): static
    {
        return new static($options, $selected);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->dropdown($name, $this->options, $this->selected);
    }

    public function kind(): string
    {
        return 'dropdown';
    }

    /**
     * Run $handler when the user chooses, with the index, the option and the mail.
     *
     * @param Closure(int, string, SelectionChanged): mixed $handler
     */
    public function onChange(Closure $handler): static
    {
        return $this->on('change', static fn (SelectionChanged $mail) => $handler($mail->index, (string) $mail->option, $mail));
    }

    /**
     * The options and the selection are one setting: new options keep the selection only
     * while it is in range, so the selection is set after them, against what the primitive
     * holds.
     */
    protected function tracked(): array
    {
        return ['choice'];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'choice' => function (TKDropdown $p, array $v): void {
                [$options, $selected] = $v;
                if ($p->options() !== $options) {
                    $p->setOptions($options);
                }
                if ($options !== [] && $p->selected() !== $selected) {
                    $p->select($selected);
                }
            },
        ];
    }

    protected function valueOf(string $prop): mixed
    {
        return $prop === 'choice' ? [$this->options, $this->selected] : parent::valueOf($prop);
    }

    protected function initial(string $prop): mixed
    {
        return $prop === 'choice' ? [$this->options, $this->selected] : parent::initial($prop);
    }
}
