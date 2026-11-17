<?php

namespace Surface\Contracts\Typography;

/** Text laid out in lines: what it measures is what it draws. */
final readonly class TypesetText
{
    /** @param  list<TypesetLine>  $lines */
    public function __construct(
        public array $lines,
        public float $width,
        public float $height,
    ) {}

    /** @return list<GlyphRun> every line's runs, in order */
    public function runs(): array
    {
        return array_merge(...array_map(fn (TypesetLine $line) => $line->runs, $this->lines));
    }
}
