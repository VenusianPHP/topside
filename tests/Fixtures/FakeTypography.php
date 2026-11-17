<?php

declare(strict_types=1);

use Surface\Contracts\Typography\FontCatalog;
use Surface\Contracts\Typography\FontFace;
use Surface\Contracts\Typography\FontMetrics;
use Surface\Contracts\Typography\FontRequest;
use Surface\Contracts\Typography\FontShaper;
use Surface\Contracts\Typography\FontSource;
use Surface\Contracts\Typography\ShapedRun;
use Surface\NutsAndBolts\Path;
use Surface\Typography\Catalogs\FontCatalogDriver;
use Surface\Typography\Faces\FontFaceDriver;
use Surface\Typography\Shapers\FontShaperDriver;
use Surface\Typography\Typesetter;

/**
 * A face with 1000 units an em that has a glyph (numbered as its codepoint) for each codepoint it
 * covers, every one 500 units wide unless given otherwise.
 */
final class FakeFace implements FontFace
{
    /**
     * @param  list<int>  $covers
     * @param  array{int, int, int}  $vertical  ascent, descent, line gap in font units
     * @param  array<int, array{float, float}>  $offsets  codepoint => where the shaper puts it from the pen, y up
     * @param  array<int, Path>  $outlines  glyph => its outline in font units, y up; any other glyph has none
     */
    public function __construct(
        public readonly FontSource $source,
        public readonly array $covers,
        public readonly array $vertical = [800, 200, 0],
        public readonly int $width = 500,
        public readonly array $offsets = [],
        public readonly array $outlines = [],
    ) {}

    /** How many times each glyph's outline was asked for. */
    public array $asked = [];

    public function source(): FontSource { return $this->source; }

    public function unitsPerEm(): int { return 1000; }

    public function glyphCount(): int { return 0x110000; }

    public function metrics(float $size): FontMetrics
    {
        return new FontMetrics($this->vertical[0] * $size / 1000, $this->vertical[1] * $size / 1000, $this->vertical[2] * $size / 1000);
    }

    public function glyph(int $codepoint): int { return in_array($codepoint, $this->covers, true) ? $codepoint : 0; }

    public function advance(int $glyph): int { return $this->width; }

    public function outline(int $glyph): Path
    {
        $this->asked[$glyph] = ($this->asked[$glyph] ?? 0) + 1;

        return $this->outlines[$glyph] ?? new Path();
    }
}

/** The faces, by source; a source it was not given is a test's mistake. */
final class FakeFaces extends FontFaceDriver
{
    /** @var array<string, FakeFace> */
    public array $faces = [];

    public function __construct(FakeFace ...$faces)
    {
        foreach ($faces as $face) {
            $this->faces[$face->source->key()] = $face;
        }
    }

    public function driver(): string { return 'fake'; }

    public function face(FontSource $source): FontFace
    {
        return $this->faces[$source->key()] ?? throw new LogicException("No fake face for {$source->key()}.");
    }

    public function release(): void {}
}

/** Shapes with the fake faces: one glyph a character, the face's width, the face's offsets. */
final class FakeShaper extends FontShaperDriver implements FontShaper
{
    public function __construct(private readonly FakeFaces $faces) {}

    public function driver(): string { return 'fake'; }

    public function shaper(): FontShaper { return $this; }

    public function shape(string $text, FontSource $source, float $size): ShapedRun
    {
        $face = $this->faces->face($source);
        [$glyphs, $clusters, $advances, $xs, $ys] = [[], [], [], [], []];
        preg_match_all('/./su', $text, $characters, PREG_OFFSET_CAPTURE);
        foreach ($characters[0] as [$character, $offset]) {
            $codepoint = mb_ord($character);
            $glyphs[] = $face->glyph($codepoint);
            $clusters[] = $offset;
            $advances[] = $face->width * $size / 1000;
            $xs[] = $face->offsets[$codepoint][0] ?? 0.0;
            $ys[] = $face->offsets[$codepoint][1] ?? 0.0;
        }

        return new ShapedRun($source, $size, $glyphs, $clusters, $advances, $xs, $ys);
    }
}

/** Matches every request to the first face; covers a codepoint with the first other face that has it. */
final class FakeCatalog extends FontCatalogDriver implements FontCatalog
{
    public function __construct(private readonly FakeFaces $faces) {}

    public function driver(): string { return 'fake'; }

    public function catalog(): FontCatalog { return $this; }

    public function match(FontRequest $request): FontSource
    {
        return array_values($this->faces->faces)[0]->source;
    }

    public function covering(FontRequest $request, int $codepoint): ?FontSource
    {
        foreach (array_slice(array_values($this->faces->faces), 1) as $face) {
            if ($face->glyph($codepoint) !== 0) {
                return $face->source;
            }
        }

        return null;
    }
}

/** A typesetter over fake faces: the first is what every font matches, the rest are fallbacks. */
function fakeTypesetter(FakeFace ...$faces): Typesetter
{
    $drivers = new FakeFaces(...$faces);

    return new Typesetter(new FakeCatalog($drivers), $drivers, new FakeShaper($drivers));
}
