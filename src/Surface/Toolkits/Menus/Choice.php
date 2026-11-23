<?php

namespace Surface\Toolkits\Menus;

use Closure;

/**
 * A radio group: one item per option, exactly one of them chosen.
 *
 *     Choice::make(['fit' => 'Fit', 'full' => 'Actual Size'], 'fit', id: 'zoom')
 *     Choice::make($options, $this->zoom, onChoose: fn (string $zoom) => $this->zoom = $zoom)
 *
 * The group is one value, so "exactly one chosen" cannot be written wrong. An id or a
 * callback is required.
 */
final readonly class Choice extends Node
{
    /**
     * @param array<string|int, string> $options value => label, in the order shown
     * @param string $chosen the value of the option that starts chosen
     * @throws MenuException When there is no option, a label is empty, $chosen is not an option,
     *         the id is not well formed, or it has neither id nor callback.
     */
    public function __construct(
        public array $options,
        public string $chosen,
        public ?string $id = null,
        public ?Closure $onChoose = null,
        public bool $enabled = true,
    ) {
        if ($options === []) {
            throw new MenuException('A menu choice needs at least one option.');
        }
        foreach ($options as $value => $label) {
            if (! is_string($label) || $label === '') {
                throw new MenuException("Menu choice: the option '{$value}' needs a label.");
            }
        }
        if (! array_key_exists($chosen, $options)) {
            throw new MenuException("Menu choice: '{$chosen}' is not one of its options (".implode(', ', array_keys($options)).').');
        }
        self::checkId($id, $this->describe());
        if (is_null($id) && is_null($onChoose)) {
            throw new MenuException(ucfirst($this->describe())." does nothing. Give it id: (it posts menu.chosen.<menu id>.<item id>) or onChoose:.");
        }
    }

    /**
     * @param array<string|int, string> $options value => label
     * @param (Closure(string): mixed)|null $onChoose run with the value when the user chooses another
     */
    public static function make(array $options, string $chosen, ?string $id = null, ?Closure $onChoose = null): self
    {
        return new self($options, $chosen, $id, $onChoose);
    }

    /**
     * Run $callback with the value when the user chooses another: how a page takes over a
     * choice it inherited.
     *
     * @param Closure(string): mixed $callback
     */
    public function onChoose(Closure $callback): self
    {
        return $this->with(['onChoose' => $callback]);
    }

    public function chosen(string $value): self
    {
        return $this->with(['chosen' => $value]);
    }

    public function enabled(bool $enabled = true): self
    {
        return $this->with(['enabled' => $enabled]);
    }

    public function key(): ?string
    {
        return $this->id ?? 'choice:'.implode('|', array_keys($this->options));
    }

    public function mailId(): ?string
    {
        return $this->id;
    }

    public function callback(): ?Closure
    {
        return $this->onChoose;
    }

    public function describe(): string
    {
        return 'choice ('.implode(', ', $this->options).')';
    }
}
