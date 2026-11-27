<?php

namespace Surface\Toolkits\UI;

use Surface\Toolkits\ToolkitException;
use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\Mail\Views\SelectionChanged;
use Surface\Contracts\Toolkits\UI\TKDropdown as PrimitiveContract;

/**
 * One choice from a list of strings: the options, which one is selected, and the user's
 * choice. The selection is -1 exactly when there are no options.
 *
 * The toolkit's popup tells this one the user chose, by calling chosen() from the native's
 * own signal; the mail is made here, so it is the same on every toolkit.
 */
abstract class TKDropdown extends TKPrimitive implements PrimitiveContract
{
    /** @var list<string> */
    private array $options;

    private int $selected;

    /** @var list<array{file: string|null, symbol: string|null}|null> */
    private array $icons = [];

    private bool $icon_only = false;

    /**
     * The options and the selection are known before the native is built, so it is built
     * with them. Every refusal comes before a native exists.
     *
     * @param list<string> $options
     * @param int $selected ignored when there are no options
     * @throws ToolkitException When the options are not a list of strings, or $selected is out of range.
     */
    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
        array $options = [],
        int $selected = 0,
    ) {
        $this->options = self::guardOptions($options, $name);
        $this->selected = $options === [] ? -1 : $this->guardIndex($selected, $name);

        parent::__construct($name, $window, $parent, $placement);
    }

    public function options(): array
    {
        return $this->options;
    }

    /**
     * The selection is kept when still in range, else the first option (-1 when empty).
     * Posts nothing.
     *
     * @throws ToolkitException When removed, or the options are not a list of strings.
     */
    public function setOptions(array $options): static
    {
        $this->guardLive();

        $this->options = self::guardOptions($options, $this->path());
        $this->selected = match (true) {
            $options === [] => -1,
            $this->selected >= 0 && $this->selected < count($options) => $this->selected,
            default => 0,
        };
        $this->applyOptions($this->options, $this->selected);
        if ($this->icons !== []) {
            $this->applyIcons($this->icons);
        }

        return $this;
    }

    public function selected(): int
    {
        return $this->selected;
    }

    /**
     * Posts nothing: the app changed it, it knows. Stored before the native is told, so a
     * toolkit that signals it reaches chosen() with the selection already held.
     *
     * @throws ToolkitException When removed, or the index is out of range.
     */
    public function select(int $index): static
    {
        $this->guardLive();

        $this->selected = $this->guardIndex($index, $this->path());
        $this->applySelected($index);

        return $this;
    }

    public function selectedOption(): ?string
    {
        return $this->options[$this->selected] ?? null;
    }

    /**
     * @throws ToolkitException When removed, or the icons are not a list of entries with a file
     *                          and a symbol (each a string or null), or null.
     */
    public function setIcons(array $icons): static
    {
        $this->guardLive();

        $this->icons = self::guardIcons($icons, $this->path());
        $this->applyIcons($this->icons);

        return $this;
    }

    public function icons(): array
    {
        return $this->icons;
    }

    /**
     * @throws ToolkitException When removed.
     */
    public function setIconOnly(bool $on = true): static
    {
        $this->guardLive();

        if ($on !== $this->icon_only) {
            $this->icon_only = $on;
            $this->applyIconOnly($on);
        }

        return $this;
    }

    public function isIconOnly(): bool
    {
        return $this->icon_only;
    }

    /**
     * The icon option $index shows: its file when there is one, else its symbol; null for none.
     *
     * @return array{file: string|null, symbol: string|null}|null
     */
    protected function iconOf(int $index): ?array
    {
        $icon = $this->icons[$index] ?? null;

        return is_null($icon) || (is_null($icon['file']) && is_null($icon['symbol'])) ? null : $icon;
    }

    /**
     * The user chose option $index: record it and post SelectionChanged. Nothing when it is
     * the selection already held, out of range, from a dropdown that is going, or while it
     * or a container above it is disabled.
     */
    protected function chosen(int $index): void
    {
        if ($index === $this->selected || $index < 0 || $index >= count($this->options) || $this->isRemoved() || ! $this->takesInput()) {
            return;
        }

        $this->selected = $index;
        $this->window()->post(new SelectionChanged($this->window()->name(), $this->path(), $this->uuid(), $index, $this->options[$index]));
    }

    /**
     * @return list<string>
     * @throws ToolkitException When not a list of strings.
     */
    private static function guardOptions(array $options, string $where): array
    {
        if (! array_is_list($options) || array_filter($options, static fn (mixed $option): bool => ! is_string($option)) !== []) {
            throw new ToolkitException("A dropdown's options are a list of strings, for '{$where}'.");
        }

        return $options;
    }

    /**
     * Each entry null, or an array of a file and a symbol, each a string or null; an empty
     * string is null.
     *
     * @return list<array{file: string|null, symbol: string|null}|null>
     * @throws ToolkitException When not such a list.
     */
    private static function guardIcons(array $icons, string $where): array
    {
        if (! array_is_list($icons)) {
            throw new ToolkitException("A dropdown's icons are a list, one for each option, for '{$where}'.");
        }

        return array_map(static function (mixed $icon) use ($where): ?array {
            if (is_null($icon)) {
                return null;
            }
            $known = ['file' => null, 'symbol' => null];
            if (! is_array($icon) || array_diff_key($icon, $known) !== []
                || array_filter($icon, static fn (mixed $v): bool => ! is_null($v) && ! is_string($v)) !== []) {
                throw new ToolkitException("A dropdown's icon is null or ['file' => ?string, 'symbol' => ?string], for '{$where}'.");
            }
            $icon += $known;

            return ['file' => $icon['file'] === '' ? null : $icon['file'], 'symbol' => $icon['symbol'] === '' ? null : $icon['symbol']];
        }, $icons);
    }

    /**
     * @throws ToolkitException When outside the options.
     */
    private function guardIndex(int $index, string $where): int
    {
        $count = count($this->options);
        if ($index < 0 || $index >= $count) {
            throw new ToolkitException("Option {$index} is out of range for '{$where}', which has {$count}.");
        }

        return $index;
    }

    /**
     * Replace the native's items, then select $selected among them (-1: none, no options).
     *
     * @param list<string> $options
     */
    abstract protected function applyOptions(array $options, int $selected): void;

    /**
     * @param int $index an option's index
     */
    abstract protected function applySelected(int $index): void;

    /**
     * Give each of the native's items its icon, by index: iconOf() says which, null for none.
     * Also called after applyOptions() while there are icons, so rebuilt items get theirs.
     *
     * @param list<array{file: string|null, symbol: string|null}|null> $icons
     */
    abstract protected function applyIcons(array $icons): void;

    /**
     * True: no border, and the selected option's icon alone on its face. False: the toolkit's
     * own dropdown, with the selected option's label.
     */
    abstract protected function applyIconOnly(bool $on): void;
}
