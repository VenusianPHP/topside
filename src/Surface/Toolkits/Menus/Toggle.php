<?php

namespace Surface\Toolkits\Menus;

use Closure;

/**
 * An item with a check mark.
 *
 *     Toggle::make('Word Wrap', on: true, id: 'wrap')       in menu 'view', posts menu.toggled.view.wrap
 *     Toggle::make('Word Wrap', on: $this->wrap, onToggle: fn (bool $on) => $this->wrap = $on)
 *
 * $on is what it starts as, and what a later definition changes it to. An id or a callback is
 * required.
 */
final readonly class Toggle extends Node
{
    /**
     * @throws MenuException When the label is empty, the id is not well formed, or it has neither id nor callback.
     */
    public function __construct(
        public string $label,
        public bool $on,
        public ?string $id = null,
        public ?Closure $onToggle = null,
        public ?Shortcut $shortcut = null,
        public bool $enabled = true,
    ) {
        if ($label === '') {
            throw new MenuException('A menu toggle needs a label.');
        }
        self::checkId($id, $this->describe());
        if (is_null($id) && is_null($onToggle)) {
            throw new MenuException(ucfirst($this->describe())." does nothing. Give it id: (it posts menu.toggled.<menu id>.<item id>) or onToggle:.");
        }
    }

    /**
     * @param (Closure(bool): mixed)|null $onToggle run with the new state when the user flips it
     */
    public static function make(string $label, bool $on, ?string $id = null, ?Closure $onToggle = null): self
    {
        return new self($label, $on, $id, $onToggle);
    }

    /**
     * Run $callback with the new state when the user flips it: how a page takes over a toggle
     * it inherited.
     *
     * @param Closure(bool): mixed $callback
     */
    public function onToggle(Closure $callback): self
    {
        return $this->with(['onToggle' => $callback]);
    }

    public function on(bool $on = true): self
    {
        return $this->with(['on' => $on]);
    }

    public function shortcut(?Shortcut $shortcut): self
    {
        return $this->with(['shortcut' => $shortcut]);
    }

    public function enabled(bool $enabled = true): self
    {
        return $this->with(['enabled' => $enabled]);
    }

    public function label(string $label): self
    {
        return $this->with(['label' => $label]);
    }

    public function key(): ?string
    {
        return $this->id ?? $this->label;
    }

    public function mailId(): ?string
    {
        return $this->id;
    }

    public function callback(): ?Closure
    {
        return $this->onToggle;
    }

    public function describe(): string
    {
        return "toggle '{$this->label}'";
    }
}
