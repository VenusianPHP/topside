<?php

namespace Surface\Toolkits\Menus;

use Closure;

/**
 * Something to choose in a menu.
 *
 *     Item::make('Save', id: 'save')                        in menu 'file', posts menu.clicked.file.save
 *     Item::make('Save', onClick: $this->save(...))         runs the callback
 *
 * One of the two is required, so an item that does nothing cannot be built. With an id alone
 * it can be written in a config file.
 */
final readonly class Item extends Node
{
    /**
     * @throws MenuException When the label is empty, the id is not well formed, or it has neither id nor callback.
     */
    public function __construct(
        public string $label,
        public ?string $id = null,
        public ?Closure $onClick = null,
        public ?Shortcut $shortcut = null,
        public bool $enabled = true,
    ) {
        if ($label === '') {
            throw new MenuException('A menu item needs a label.');
        }
        self::checkId($id, $this->describe());
        if (is_null($id) && is_null($onClick)) {
            throw new MenuException(ucfirst($this->describe())." does nothing. Give it id: (it posts menu.clicked.<menu id>.<item id>) or onClick:.");
        }
    }

    public static function make(string $label, ?string $id = null, ?Closure $onClick = null): self
    {
        return new self($label, $id, $onClick);
    }

    /**
     * Run $callback when the item is chosen.
     */
    public function onClick(Closure $callback): self
    {
        return $this->with(['onClick' => $callback]);
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
        return $this->onClick;
    }

    public function describe(): string
    {
        return "item '{$this->label}'";
    }
}
