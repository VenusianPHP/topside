<?php

namespace Surface\Toolkits\Menus;

use Closure;
use Surface\Contracts\Toolkits\Menus\StandardKind;

/**
 * An item the platform knows: Quit, Copy, Minimize. Made by Standard::*() and About::*().
 * Its kind gives the platform's usual label and shortcut, and for most kinds the toolkit does
 * the work. label() and shortcut() replace the first two; id() (mail, for a config file) or
 * onClick() (a callback) replaces the work.
 */
final readonly class StandardItem extends Node
{
    /**
     * @param string|null $label the label instead of the platform's
     * @param Shortcut|false|null $shortcut a shortcut instead of the platform's; false for none
     * @param AboutPanel|null $panel for About: what the toolkit's own panel shows
     * @throws MenuException When the id is not well formed, a panel is given to a kind other than About, or
     *         an About or Preferences does nothing.
     */
    public function __construct(
        public StandardKind $kind,
        public ?string $id = null,
        public ?string $label = null,
        public Shortcut|false|null $shortcut = null,
        public ?Closure $onClick = null,
        public bool $enabled = true,
        public ?AboutPanel $panel = null,
    ) {
        self::checkId($id, $this->describe());

        if (! is_null($panel) && $kind !== StandardKind::ABOUT) {
            throw new MenuException("Only About takes a panel, not {$this->describe()}.");
        }
        if ($label === '') {
            throw new MenuException(ucfirst($this->describe()).': the label cannot be empty.');
        }
        if (! $this->isReplaced() && is_null($panel) && ($kind === StandardKind::ABOUT || $kind === StandardKind::PREFERENCES)) {
            $instead = $kind === StandardKind::ABOUT ? ', or use About::panel()' : '';
            throw new MenuException(ucfirst($this->describe())." does nothing. Give it id: (it posts menu.clicked.<menu id>.<item id>) or onClick:{$instead}.");
        }
    }

    public function label(string $label): self
    {
        return $this->with(['label' => $label]);
    }

    /**
     * A shortcut instead of the platform's; null for none at all.
     */
    public function shortcut(?Shortcut $shortcut): self
    {
        return $this->with(['shortcut' => $shortcut ?? false]);
    }

    /**
     * Run $callback instead of what the kind does by itself.
     */
    public function onClick(Closure $callback): self
    {
        return $this->with(['onClick' => $callback]);
    }

    /**
     * Post menu.clicked.{path} instead of what the kind does by itself.
     */
    public function id(string $id): self
    {
        return $this->with(['id' => $id]);
    }

    /**
     * Whether the application answers it, by id() or onClick(), instead of the toolkit.
     */
    public function isReplaced(): bool
    {
        return ! is_null($this->id) || ! is_null($this->onClick);
    }

    public function enabled(bool $enabled = true): self
    {
        return $this->with(['enabled' => $enabled]);
    }

    /**
     * The label on a platform: the one given, else the platform's own.
     *
     * @param string $app the application's name, for "Quit My App"
     */
    public function labelOn(bool $mac, string $app): string
    {
        return $this->label ?? match ($this->kind) {
            StandardKind::ABOUT => "About {$app}",
            StandardKind::PREFERENCES => $mac ? 'Settings…' : 'Preferences',
            StandardKind::QUIT => $mac ? "Quit {$app}" : 'Quit',
            StandardKind::HIDE => "Hide {$app}",
            StandardKind::HIDE_OTHERS => 'Hide Others',
            StandardKind::SHOW_ALL => 'Show All',
            StandardKind::MINIMIZE => 'Minimize',
            StandardKind::CLOSE_WINDOW => $mac ? 'Close Window' : 'Close',
            StandardKind::FULLSCREEN => $mac ? 'Enter Full Screen' : 'Fullscreen',
            StandardKind::UNDO => 'Undo',
            StandardKind::REDO => 'Redo',
            StandardKind::CUT => 'Cut',
            StandardKind::COPY => 'Copy',
            StandardKind::PASTE => 'Paste',
            StandardKind::PASTE_AND_MATCH_STYLE => 'Paste and Match Style',
            StandardKind::SELECT_ALL => 'Select All',
        };
    }

    /**
     * The shortcut on a platform: the one given, none when it was taken off, else the platform's own.
     */
    public function shortcutOn(bool $mac): ?Shortcut
    {
        if ($this->shortcut instanceof Shortcut) {
            return $this->shortcut;
        }
        if ($this->shortcut === false) {
            return null;
        }

        return match ($this->kind) {
            StandardKind::QUIT => Shortcut::primary('Q'),
            StandardKind::PREFERENCES => $mac ? Shortcut::primary(',') : null,
            StandardKind::HIDE => Shortcut::primary('H'),
            StandardKind::HIDE_OTHERS => Shortcut::primary('H')->alt(),
            StandardKind::MINIMIZE => $mac ? Shortcut::primary('M') : null,
            StandardKind::CLOSE_WINDOW => Shortcut::primary('W'),
            StandardKind::FULLSCREEN => $mac ? Shortcut::primary('F')->ctrl() : Shortcut::key('F11'),
            StandardKind::UNDO => Shortcut::primary('Z'),
            StandardKind::REDO => Shortcut::primary('Z')->shift(),
            StandardKind::CUT => Shortcut::primary('X'),
            StandardKind::COPY => Shortcut::primary('C'),
            StandardKind::PASTE => Shortcut::primary('V'),
            StandardKind::PASTE_AND_MATCH_STYLE => $mac ? Shortcut::primary('V')->alt()->shift() : Shortcut::primary('V')->shift(),
            StandardKind::SELECT_ALL => Shortcut::primary('A'),
            default => null,
        };
    }

    public function key(): ?string
    {
        return $this->id ?? $this->kind->value;
    }

    public function mailId(): ?string
    {
        return $this->id;
    }

    public function callback(): ?Closure
    {
        return $this->onClick;
    }

    public function shownOn(bool $mac): bool
    {
        return $mac || ! $this->kind->isMacOnly();
    }

    public function describe(): string
    {
        return "standard item '{$this->kind->value}'";
    }
}
