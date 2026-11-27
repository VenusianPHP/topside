<?php

namespace Surface\Contracts\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\Styling\TextRun;
use Surface\Contracts\Toolkits\Styling\TextAlignment;
use Surface\NutsAndBolts\Color;

/**
 * Static text. Posts no mail.
 */
interface TKLabel extends TKPrimitive
{
    /**
     * @return string
     */
    public function text(): string;

    /**
     * @param string $text
     * @return $this
     */
    public function setText(string $text): static;

    /**
     * The text as runs, each bold, italic or coloured on its own; text() is their text joined.
     * setText() makes the label one plain run again. Where the toolkit cannot show a run's
     * look, it shows the run as the label's own.
     * @param list<TextRun> $runs
     * @return $this
     */
    public function setRuns(array $runs): static;

    /**
     * @return list<TextRun> the runs setRuns() set; one plain run of text() after setText()
     */
    public function runs(): array;

    /**
     * Wrap at the allocated width instead of growing.
     * @param bool $wrap
     * @return $this
     */
    public function setWrap(bool $wrap): static;

    /**
     * One line, cut at the end with "…" where the text is wider than the label's room, and
     * never wider than that room: a long text does not widen its container. While it
     * truncates, the label is one line whatever setWrap() said.
     * @param bool $truncate
     * @return $this
     */
    public function setTruncate(bool $truncate): static;

    /**
     * @param TextAlignment $alignment
     * @return $this
     */
    public function setAlignment(TextAlignment $alignment): static;

    /**
     * @param FontSpec $font
     * @return $this
     */
    public function setFont(FontSpec $font): static;

    /**
     * @param Color|null $color null restores the toolkit's own
     * @return $this
     */
    public function setTextColor(?Color $color): static;
}
