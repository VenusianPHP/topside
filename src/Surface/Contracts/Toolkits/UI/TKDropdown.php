<?php

namespace Surface\Contracts\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\TKPrimitive;
use RuntimeException;

/**
 * One choice from a list of strings. Posts SelectionChanged.
 */
interface TKDropdown extends TKPrimitive
{
    /**
     * @return list<string>
     */
    public function options(): array;

    /**
     * The selection is kept when still in range, else the first option (-1 when empty).
     * @param list<string> $options
     * @return $this
     */
    public function setOptions(array $options): static;

    /**
     * @return int the selected index, -1 when there are no options
     */
    public function selected(): int;

    /**
     * Posts nothing: the app changed it, it knows.
     * @param int $index
     * @return $this
     * @throws RuntimeException When the index is out of range.
     */
    public function select(int $index): static;

    /**
     * @return string|null null when there are no options
     */
    public function selectedOption(): ?string;

    /**
     * An icon for each option, by index: a file, or the toolkit's own icon by its name for it (an
     * SF Symbol on AppKit). The file shows when there is one, else the symbol, as on a button. An
     * option with null, or past the list, has none. Kept when the options change.
     *
     * @param list<array{file?: string|null, symbol?: string|null}|null> $icons
     * @return $this
     * @throws RuntimeException When removed, or the icons are not a list of such entries.
     */
    public function setIcons(array $icons): static;

    /**
     * @return list<array{file: string|null, symbol: string|null}|null>
     */
    public function icons(): array;

    /**
     * Shown as the selected option's icon alone, with no border; the list it opens still shows
     * each option's icon and label.
     *
     * @return $this
     * @throws RuntimeException When removed.
     */
    public function setIconOnly(bool $on = true): static;

    public function isIconOnly(): bool;
}
