<?php

namespace Surface\Contracts\Toolkits\Styling;

use Surface\NutsAndBolts\Color;

/**
 * A piece of a label's text with its own look. What a run leaves unset is the label's own:
 * its font for a run that is not bold or italic, its text colour for a run with no colour.
 */
final readonly class TextRun
{
    /**
     * @param string $text
     * @param bool $bold the label's font, bold
     * @param bool $italic the label's font, italic
     * @param Color|null $color null: the label's text colour
     */
    public function __construct(
        public string $text,
        public bool $bold = false,
        public bool $italic = false,
        public ?Color $color = null,
    ) {}

    /**
     * True when the run looks like the label around it.
     */
    public function isPlain(): bool
    {
        return ! $this->bold && ! $this->italic && is_null($this->color);
    }

    /**
     * @param list<TextRun> $runs
     */
    public static function join(array $runs): string
    {
        return implode('', array_map(static fn (TextRun $run): string => $run->text, $runs));
    }
}
