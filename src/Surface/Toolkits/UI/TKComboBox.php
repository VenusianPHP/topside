<?php

namespace Surface\Toolkits\UI;

use Surface\Toolkits\ToolkitException;
use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\Mail\Views\TextChanged;
use Surface\Contracts\Toolkits\Mail\Views\TextSubmitted;
use Surface\Contracts\Toolkits\Mail\Views\SelectionChanged;
use Surface\Contracts\Toolkits\UI\TKComboBox as PrimitiveContract;
use Surface\Toolkits\UI\Concerns\IgnoresItsOwnWrites;

/**
 * An editable field with options and a completion list: the value, both lists, and the rules
 * for when the completions show and go.
 *
 * The toolkit says what the user did, through typed(), submitted(), choseOption(),
 * choseCompletion() and completionsHidden(); the mail is made here.
 */
abstract class TKComboBox extends TKPrimitive implements PrimitiveContract
{
    use IgnoresItsOwnWrites;

    /** @var list<string> */
    private array $options;

    /** @var list<string> what the list shows */
    private array $completions = [];

    /**
     * @param list<string> $options
     * @throws ToolkitException When the options are not a list of strings; before any native exists.
     */
    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
        private string $value = '',
        array $options = [],
    ) {
        $this->options = self::guardStrings($options, 'options', $name);

        parent::__construct($name, $window, $parent, $placement);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->guardLive();

        $this->value = $value;
        $this->completions = [];
        $this->write(function () use ($value): void {
            $this->applyCompletions([]);
            $this->applyValue($value);
        });

        return $this;
    }

    public function options(): array
    {
        return $this->options;
    }

    /**
     * The value stays, whether or not it is one of them.
     */
    public function setOptions(array $options): static
    {
        $this->guardLive();

        $this->options = self::guardStrings($options, 'options', $this->path());
        $this->write(function (): void {
            $this->applyOptions($this->options);
            $this->applyValue($this->value);
        });

        return $this;
    }

    public function completions(): array
    {
        return $this->completions;
    }

    public function setCompletions(array $completions): static
    {
        $this->guardLive();
        $completions = self::guardStrings($completions, 'completions', $this->path());

        $this->completions = $this->hasFocus() ? $completions : [];
        $this->write(fn () => $this->applyCompletions($this->completions));

        return $this;
    }

    public function focusMovedAway(): void
    {
        $this->completionsHidden();
    }

    protected function typed(string $value): void
    {
        if ($value === $this->value || ! $this->hearsUser()) {
            return;
        }

        $this->value = $value;
        $this->window()->post(new TextChanged($this->window()->name(), $this->path(), $this->uuid(), $value));
    }

    /**
     * Return, with no completion highlighted.
     */
    protected function submitted(string $value): void
    {
        if (! $this->hearsUser()) {
            return;
        }

        $this->typed($value);
        $this->completionsHidden();
        $this->window()->post(new TextSubmitted($this->window()->name(), $this->path(), $this->uuid(), $this->value));
    }

    protected function choseOption(int $index): void
    {
        if (isset($this->options[$index])) {
            $this->chose($index, $this->options[$index]);
        }
    }

    /**
     * By its text: a toolkit may hide the list before it says which was chosen.
     */
    protected function choseCompletion(string $text): void
    {
        $this->chose(-1, $text);
    }

    /**
     * The list went: Esc, focus leaving, a click outside.
     */
    protected function completionsHidden(): void
    {
        if ($this->isWriting() || $this->isRemoved() || $this->completions === []) {
            return;
        }

        $this->completions = [];
        $this->write(fn () => $this->applyCompletions([]));
    }

    private function chose(int $index, string $text): void
    {
        if (! $this->hearsUser()) {
            return;
        }

        $this->value = $text;
        $this->completions = [];
        $this->write(function () use ($text): void {
            $this->applyCompletions([]);
            $this->applyValue($text);
        });
        $this->window()->post(new SelectionChanged($this->window()->name(), $this->path(), $this->uuid(), $index, $text));
    }

    /**
     * @return list<string>
     * @throws ToolkitException
     */
    private static function guardStrings(array $strings, string $what, string $where): array
    {
        if (! array_is_list($strings) || array_filter($strings, static fn (mixed $string): bool => ! is_string($string)) !== []) {
            throw new ToolkitException("A combo box's {$what} are a list of strings, for '{$where}'.");
        }

        return $strings;
    }

    abstract protected function applyValue(string $value): void;

    /**
     * @param list<string> $options
     */
    abstract protected function applyOptions(array $options): void;

    /**
     * Show these under the field with none highlighted, without taking its focus; [] hides the list.
     *
     * @param list<string> $completions
     */
    abstract protected function applyCompletions(array $completions): void;
}
