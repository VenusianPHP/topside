<?php

namespace Surface\Toolkits\Views\Elements;

use Closure;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\UI\TKSegmentedControl;
use Surface\Contracts\Toolkits\Mail\Views\SelectionChanged;

/**
 * One choice from a few, all of them showing. It is written as a Dropdown is:
 *
 *     <SegmentedControl :options="['Day', 'Week']" :selected="$span" @change="fn ($i) => $this->span = $i" />
 */
class SegmentedControl extends Element
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
        return $parent->segmentedControl($name, $this->options, $this->selected);
    }

    public function kind(): string
    {
        return 'segmentedControl';
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
     * The options and the selection are one setting, as a Dropdown's are.
     */
    protected function made(): array
    {
        return ['choice' => [$this->options, $this->selected]];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'choice' => function (TKSegmentedControl $p, array $v): void {
                [$options, $selected] = $v;
                if ($p->segments() !== $options) {
                    $p->setSegments($options);
                }
                if ($options !== [] && $p->selected() !== $selected) {
                    $p->select($selected);
                }
            },
        ];
    }
}
