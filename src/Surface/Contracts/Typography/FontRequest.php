<?php

namespace Surface\Contracts\Typography;

/**
 * What a font is asked for by: family names in order of preference, a weight and a slant. A family
 * may be a generic one ('sans-serif', 'serif', 'monospace'), which the catalog resolves.
 */
final readonly class FontRequest
{
    /**
     * @param  list<string>  $families
     * @param  int  $weight  1..1000, 400 regular, 700 bold
     *
     * @throws TypographyException When no family is named, a name is empty, or the weight is outside 1..1000.
     */
    public function __construct(
        public array $families = ['sans-serif'],
        public int $weight = 400,
        public FontSlant $slant = FontSlant::UPRIGHT,
    ) {
        if ($families === [] || ! array_is_list($families)) {
            throw new TypographyException('A font request names at least one family, as a list.');
        }
        foreach ($families as $family) {
            if (! is_string($family) || trim($family) === '') {
                throw new TypographyException('A font request\'s families are non-empty strings.');
            }
        }
        if ($weight < 1 || $weight > 1000) {
            throw new TypographyException("A font weight is 1..1000, got {$weight}.");
        }
    }

    /** One string per distinct request. */
    public function key(): string
    {
        return implode(',', array_map('strtolower', $this->families))."|{$this->weight}|{$this->slant->value}";
    }
}
