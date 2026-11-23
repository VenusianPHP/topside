<?php

namespace Surface\Toolkits\Menus;

use Surface\Contracts\Toolkits\Menus\ShortcutKey;

/**
 * The keys that choose a menu item: one key and its modifiers. Primary is Command on macOS
 * and Control everywhere else; Ctrl is Control on both.
 *
 *     Shortcut::primary('S')            Cmd+S, or Ctrl+S
 *     Shortcut::primary('S')->shift()
 *     Shortcut::key('F5')
 *     Shortcut::of('Primary+Alt+I')
 *
 * Delete, Backspace, the arrows, Home, End, PageUp, PageDown and F1 to F12 may stand alone,
 * and then the focused widget gets the key first. Every other key needs Primary, Ctrl or Alt,
 * so a shortcut never takes typed text, focus or a dialog's keys.
 */
final readonly class Shortcut
{
    use CopiesItself;

    /**
     * @throws MenuException When the key needs a modifier and has none.
     */
    public function __construct(
        public ShortcutKey $key,
        public bool $primary = false,
        public bool $shift = false,
        public bool $alt = false,
        public bool $ctrl = false,
    ) {
        if (! $key->standsAlone() && ! $primary && ! $alt && ! $ctrl) {
            throw new MenuException("Shortcut '{$this->describe()}' needs Primary, Ctrl or Alt: only Delete, Backspace, the arrows, Home, End, PageUp, PageDown and F1 to F12 work alone.");
        }
    }

    /**
     * Command and $key on macOS, Control and $key elsewhere.
     *
     * @throws MenuException When $key names no shortcut key.
     */
    public static function primary(ShortcutKey|string $key): self
    {
        return new self(self::keyOf($key), primary: true);
    }

    /**
     * $key alone, so one that may stand alone. Any other key starts with a modifier: primary(), or of('Alt+S').
     *
     * @throws MenuException When $key names no shortcut key, or needs a modifier.
     */
    public static function key(ShortcutKey|string $key): self
    {
        return new self(self::keyOf($key));
    }

    /**
     * A shortcut as written: modifiers and one key joined by '+', in any case and order.
     * 'Primary+Shift+S', 'alt+f4', 'F5'.
     *
     * @throws MenuException When a part is neither a modifier nor a key, or there is not exactly one key.
     */
    public static function of(string $written): self
    {
        $modifiers = ['primary' => false, 'shift' => false, 'alt' => false, 'ctrl' => false];
        $key = null;

        foreach (explode('+', $written) as $part) {
            $part = trim($part);
            $name = strtolower($part);

            if (array_key_exists($name, $modifiers)) {
                $modifiers[$name] = true;
                continue;
            }

            if (! is_null($key)) {
                throw new MenuException("Shortcut '{$written}' has two keys: '{$key->value}' and '{$part}'.");
            }

            $key = ShortcutKey::named($part)
                ?? throw new MenuException("Shortcut '{$written}': '{$part}' is not a modifier (Primary, Shift, Alt, Ctrl) or a shortcut key.");
        }

        if (is_null($key)) {
            throw new MenuException("Shortcut '{$written}' has no key.");
        }

        return new self($key, ...$modifiers);
    }

    public function shift(bool $on = true): self
    {
        return $this->with(['shift' => $on]);
    }

    public function alt(bool $on = true): self
    {
        return $this->with(['alt' => $on]);
    }

    public function ctrl(bool $on = true): self
    {
        return $this->with(['ctrl' => $on]);
    }

    /**
     * Whether the focused widget gets the keys before the menu does: so it is with no
     * Primary, Ctrl or Alt.
     */
    public function yieldsToFocus(): bool
    {
        return ! $this->primary && ! $this->ctrl && ! $this->alt;
    }

    /**
     * Whether the two are the same keys on a platform. Off macOS Primary is Control, so
     * Primary+S and Ctrl+S are one shortcut there and two on macOS.
     */
    public function same(self $other, bool $mac): bool
    {
        return $this->pressed($mac) === $other->pressed($mac);
    }

    /**
     * 'Primary+Shift+S' as written; for a platform, 'Cmd+Shift+S' or 'Ctrl+Shift+S'.
     */
    public function describe(?bool $mac = null): string
    {
        $parts = [];
        if ($this->primary) {
            $parts[] = match ($mac) {
                null => 'Primary',
                true => 'Cmd',
                false => 'Ctrl',
            };
        }
        if ($this->ctrl && ! ($mac === false && $this->primary)) {
            $parts[] = 'Ctrl';
        }
        if ($this->alt) {
            $parts[] = 'Alt';
        }
        if ($this->shift) {
            $parts[] = 'Shift';
        }
        $parts[] = $this->key->value;

        return implode('+', $parts);
    }

    /**
     * @return array{string, bool, bool, bool, bool} key, command, control, alt, shift as held on the platform
     */
    private function pressed(bool $mac): array
    {
        return [
            $this->key->value,
            $mac && $this->primary,
            $this->ctrl || (! $mac && $this->primary),
            $this->alt,
            $this->shift,
        ];
    }

    private static function keyOf(ShortcutKey|string $key): ShortcutKey
    {
        return $key instanceof ShortcutKey
            ? $key
            : ShortcutKey::named($key) ?? throw new MenuException("'{$key}' is not a shortcut key.");
    }
}
