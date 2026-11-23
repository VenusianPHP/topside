<?php

namespace Surface\Toolkits\Views\Elements;

use Closure;
use Surface\Contracts\Toolkits\UI\TKComboBox;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Mail\Views\TextChanged;
use Surface\Contracts\Toolkits\Mail\Views\TextSubmitted;
use Surface\Contracts\Toolkits\Mail\Views\SelectionChanged;

/**
 * A field to type in or choose from. Typed or chosen, onChange() gets the value:
 *
 *     ComboBox::make($cities, value: $this->city)
 *         ->completions($this->matches())
 *         ->onChange(fn (string $city) => $this->city = $city)
 */
class ComboBox extends Element
{
    /** @var list<string> */
    private array $completions = [];

    /**
     * @param list<string> $options
     */
    final public function __construct(
        protected readonly array $options = [],
        protected readonly string $value = '',
    ) {}

    /**
     * @param list<string> $options
     */
    public static function make(array $options = [], string $value = ''): static
    {
        return new static($options, $value);
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->comboBox($name, $this->value, $this->options);
    }

    public function kind(): string
    {
        return 'comboBox';
    }

    /**
     * Shown under the field while it has focus, whenever this list changes.
     *
     * @param list<string> $completions
     */
    public function completions(array $completions): static
    {
        $this->completions = $completions;

        return $this;
    }

    /**
     * Run $handler when the user types or chooses, with the value and the mail.
     *
     * @param Closure(string, TextChanged|SelectionChanged): mixed $handler
     */
    public function onChange(Closure $handler): static
    {
        $live = static fn (TextChanged|SelectionChanged $mail, TKComboBox $box) => $handler($box->value(), $mail);

        return $this->onLive('input', $live)->onLive('change', $live);
    }

    /**
     * Run $handler on Return, with the text and the TextSubmitted mail.
     *
     * @param Closure(string, TextSubmitted): mixed $handler
     */
    public function onSubmit(Closure $handler): static
    {
        return $this->on('submit', static fn (TextSubmitted $mail) => $handler($mail->value, $mail));
    }

    /**
     * The completions after the value: setting a value hides them.
     */
    protected function tracked(): array
    {
        return ['options', 'value', 'completions'];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            'options' => fn (TKComboBox $p, array $v) => $p->setOptions($v),
            'value' => function (TKComboBox $p, string $v): void {
                if ($p->value() !== $v) {
                    $p->setValue($v);
                }
            },
            'completions' => fn (TKComboBox $p, array $v) => $p->setCompletions($v),
        ];
    }

    protected function valueOf(string $prop): mixed
    {
        return match ($prop) {
            'options' => $this->options,
            'value' => $this->value,
            'completions' => $this->completions,
            default => parent::valueOf($prop),
        };
    }

    protected function initial(string $prop): mixed
    {
        return match ($prop) {
            'options', 'value' => $this->valueOf($prop),
            'completions' => [],
            default => parent::initial($prop),
        };
    }
}
