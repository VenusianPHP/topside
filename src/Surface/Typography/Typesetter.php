<?php

namespace Surface\Typography;

use Surface\Contracts\Typography\Font;
use Surface\Contracts\Typography\FontCatalog;
use Surface\Contracts\Typography\FontFace;
use Surface\Contracts\Typography\FontShaper;
use Surface\Contracts\Typography\GlyphRun;
use Surface\Contracts\Typography\TextAlign;
use Surface\Contracts\Typography\TypesetLine;
use Surface\Contracts\Typography\TypesetText;
use Surface\Contracts\Typography\TypographyException;
use Surface\Typography\Catalogs\FontCatalogDriver;
use Surface\Typography\Faces\FontFaceDriver;
use Surface\Typography\Shapers\FontShaperDriver;
use Surface\Typography\Support\DefaultIgnorable;

/**
 * Lays text out in lines with the three drivers: the catalog finds the face a font asks for and the
 * faces to fall back on, the face driver opens them, the shaper turns each stretch of one face into
 * glyphs. Then it breaks lines and places every glyph. It holds no pixels.
 *
 * Lines end at "\n"; "\r" is dropped. With a width, a line also breaks after a space where the next
 * word would pass the width; a word wider than the width keeps a line to itself. Each line's box is
 * as tall as the tallest face in it: its baseline is its top plus the largest ascent, and the next
 * line starts below the largest descent and line gap.
 */
final class Typesetter
{
    /** Shaped paragraphs kept, least recently used first out. */
    private const int KEPT = 512;

    /** Room for the sum of many advances to land a hair past an exact width. */
    private const float SLACK = 1e-6;

    /** @var array<string, array{runs: list<\Surface\Contracts\Typography\ShapedRun>, run: list<int>, at: list<int>, space: list<bool>}> */
    private array $shaped = [];

    public function __construct(
        private readonly FontCatalogDriver $catalogs,
        private readonly FontFaceDriver $faces,
        private readonly FontShaperDriver $shapers,
    ) {}

    /**
     * The text typeset in the font, wrapped at the width if one is given, each line aligned across
     * the width (or across the widest line when there is none).
     *
     * @throws TypographyException When the text is not UTF-8, or the width is not a finite number above 0.
     */
    public function set(string $text, Font $font, ?float $width = null, TextAlign $align = TextAlign::LEFT): TypesetText
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            throw new TypographyException('Text to typeset is UTF-8; this text is not.');
        }
        if (! is_null($width) && (! is_finite($width) || $width <= 0.0)) {
            throw new TypographyException("A width to typeset in is a finite number above 0, got {$width}.");
        }
        $primary = $this->faces->face($font->source ?? $this->catalog()->match($font->request));

        $lines = [];   // [shaped paragraph, first glyph, end glyph, width]
        foreach (explode("\n", str_replace("\r", '', $text)) as $paragraph) {
            $shaped = $this->shaped($paragraph, $font, $primary);
            foreach ($this->breaks($shaped, $width) as [$from, $to]) {
                $lines[] = [$shaped, $from, $to, $this->inkWidth($shaped, $from, $to)];
            }
        }
        $across = $width ?? max(array_map(fn (array $line) => $line[3], $lines));

        $placed = [];
        $top = 0.0;
        foreach ($lines as [$shaped, $from, $to, $lineWidth]) {
            $metrics = $this->lineMetrics($shaped, $from, $to, $primary, $font->size);
            $left = match ($align) {
                TextAlign::LEFT => 0.0,
                TextAlign::CENTER => ($across - $lineWidth) / 2,
                TextAlign::RIGHT => $across - $lineWidth,
            };
            $baseline = $top + $metrics[0];
            $height = $metrics[0] + $metrics[1] + $metrics[2];
            $placed[] = new TypesetLine($top, $baseline, $height, $left, $lineWidth, $this->runs($shaped, $from, $to, $left, $baseline));
            $top += $height;
        }

        return new TypesetText($placed, max(array_map(fn (array $line) => $line[3], $lines)), $top);
    }

    private function catalog(): FontCatalog
    {
        return $this->catalogs->catalog();
    }

    private function shaper(): FontShaper
    {
        return $this->shapers->shaper();
    }

    /**
     * A paragraph shaped: cut into stretches of one face each (the font's face, or the one the catalog
     * finds for a character it lacks), each stretch shaped, every glyph listed with its run, its
     * index in the run, and whether it is a space.
     *
     * @return array{runs: list<\Surface\Contracts\Typography\ShapedRun>, run: list<int>, at: list<int>, space: list<bool>}
     */
    private function shaped(string $paragraph, Font $font, FontFace $primary): array
    {
        $key = $font->key()."\0".$paragraph;
        if (isset($this->shaped[$key])) {
            $hit = $this->shaped[$key];
            unset($this->shaped[$key]);

            return $this->shaped[$key] = $hit;
        }
        if (count($this->shaped) >= self::KEPT) {
            unset($this->shaped[array_key_first($this->shaped)]);
        }

        $shaped = ['runs' => [], 'run' => [], 'at' => [], 'space' => []];
        foreach ($this->stretches($paragraph, $font, $primary) as [$face, $offset, $length]) {
            $run = $this->shaper()->shape(substr($paragraph, $offset, $length), $face->source(), $font->size);
            $index = count($shaped['runs']);
            $shaped['runs'][] = $run;
            foreach ($run->clusters as $at => $cluster) {
                $shaped['run'][] = $index;
                $shaped['at'][] = $at;
                $shaped['space'][] = $paragraph[$offset + $cluster] === ' ';
            }
        }

        return $this->shaped[$key] = $shaped;
    }

    /**
     * The paragraph cut where the face changes, a whole grapheme at a time.
     *
     * @return list<array{FontFace, int, int}> face, byte offset, byte length
     */
    private function stretches(string $paragraph, Font $font, FontFace $primary): array
    {
        if ($paragraph === '') {
            return [];
        }
        preg_match_all('/\X/u', $paragraph, $graphemes, PREG_OFFSET_CAPTURE);
        $stretches = [];
        $face = null;
        foreach ($graphemes[0] as [$grapheme, $offset]) {
            $face = $this->faceFor($grapheme, $font, $primary, $face);
            $last = array_key_last($stretches);
            if (! is_null($last) && $stretches[$last][0] === $face) {
                $stretches[$last][2] += strlen($grapheme);
            } else {
                $stretches[] = [$face, $offset, strlen($grapheme)];
            }
        }

        return $stretches;
    }

    /**
     * The face a grapheme is drawn with: the font's own when it has every character, else the face
     * the stretch before used when that one has them, else the one the catalog finds for its first
     * character. When no face has it, the font's own, which draws its missing glyph.
     */
    private function faceFor(string $grapheme, Font $font, FontFace $primary, ?FontFace $previous): FontFace
    {
        $codepoints = [];
        foreach (mb_str_split($grapheme) as $character) {
            $codepoint = mb_ord($character);
            if (! DefaultIgnorable::contains($codepoint)) {
                $codepoints[] = $codepoint;
            }
        }
        if ($codepoints === []) {
            return $previous ?? $primary;
        }
        if (self::covers($primary, $codepoints)) {
            return $primary;
        }
        if (! is_null($previous) && $previous !== $primary && self::covers($previous, $codepoints)) {
            return $previous;
        }
        $source = $this->catalog()->covering($font->request, $codepoints[0]);

        return is_null($source) ? $primary : $this->faces->face($source);
    }

    /** @param  list<int>  $codepoints */
    private static function covers(FontFace $face, array $codepoints): bool
    {
        foreach ($codepoints as $codepoint) {
            if ($face->glyph($codepoint) === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Where the paragraph's lines start and end, as glyph indexes [first, end). Without a width it is
     * one line. With one, a line breaks after a run of spaces when the next word's ink would pass it.
     *
     * @return list<array{int, int}>
     */
    private function breaks(array $shaped, ?float $width): array
    {
        $count = count($shaped['run']);
        if (is_null($width) || $count === 0) {
            return [[0, $count]];
        }
        $lines = [];
        $start = 0;
        $used = 0.0;
        for ($i = 0; $i < $count;) {
            $ink = $i;
            $inkWidth = 0.0;
            while ($ink < $count && ! $shaped['space'][$ink]) {
                $inkWidth += $this->advance($shaped, $ink++);
            }
            $end = $ink;
            $tail = 0.0;
            while ($end < $count && $shaped['space'][$end]) {
                $tail += $this->advance($shaped, $end++);
            }
            if ($start < $i && $used + $inkWidth > $width + self::SLACK) {
                $lines[] = [$start, $i];
                $start = $i;
                $used = 0.0;
            }
            $used += $inkWidth + $tail;
            $i = $end;
        }
        $lines[] = [$start, $count];

        return $lines;
    }

    /** The advances from first to end, without the spaces that end the line. */
    private function inkWidth(array $shaped, int $from, int $to): float
    {
        while ($to > $from && $shaped['space'][$to - 1]) {
            $to--;
        }
        $width = 0.0;
        for ($i = $from; $i < $to; $i++) {
            $width += $this->advance($shaped, $i);
        }

        return $width;
    }

    private function advance(array $shaped, int $i): float
    {
        return $shaped['runs'][$shaped['run'][$i]]->advances[$shaped['at'][$i]];
    }

    /**
     * The largest ascent, descent and line gap among the faces the line's glyphs use; the font's own
     * face for a line with none.
     *
     * @return array{float, float, float}
     */
    private function lineMetrics(array $shaped, int $from, int $to, FontFace $primary, float $size): array
    {
        $sources = [];
        for ($i = $from; $i < $to; $i++) {
            $source = $shaped['runs'][$shaped['run'][$i]]->source;
            $sources[$source->key()] = $source;
        }
        $faces = $sources === [] ? [$primary] : array_map(fn ($source) => $this->faces->face($source), array_values($sources));
        $ascent = $descent = $gap = 0.0;
        foreach ($faces as $face) {
            $metrics = $face->metrics($size);
            $ascent = max($ascent, $metrics->ascent);
            $descent = max($descent, $metrics->descent);
            $gap = max($gap, $metrics->lineGap);
        }

        return [$ascent, $descent, $gap];
    }

    /**
     * The line's glyphs placed from the pen at its left edge, one glyph run a stretch of one face.
     *
     * @return list<GlyphRun>
     */
    private function runs(array $shaped, int $from, int $to, float $left, float $baseline): array
    {
        $runs = [];
        $pen = $left;
        for ($i = $from; $i < $to;) {
            $index = $shaped['run'][$i];
            $run = $shaped['runs'][$index];
            [$glyphs, $x, $y] = [[], [], []];
            for (; $i < $to && $shaped['run'][$i] === $index; $i++) {
                $at = $shaped['at'][$i];
                $glyphs[] = $run->glyphs[$at];
                $x[] = $pen + $run->offsetsX[$at];
                $y[] = $baseline - $run->offsetsY[$at];
                $pen += $run->advances[$at];
            }
            $runs[] = new GlyphRun($run->source, $run->size, $glyphs, $x, $y);
        }

        return $runs;
    }
}
