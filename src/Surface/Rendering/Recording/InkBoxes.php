<?php

namespace Surface\Rendering\Recording;

use Surface\Contracts\Typography\FontSource;
use Surface\Contracts\Typography\TypesetText;
use Surface\Contracts\Typography\TypographyException;
use Surface\Typography\Faces\FontFaceDriver;
use WeakMap;

/**
 * Where typeset text puts ink: the box of its glyphs' outlines, from the top-left of the text, y
 * down. A recorder needs it for the reach of a 'glyphs' command, which says what a frame redraws.
 *
 * A glyph's box is the box of its outline's points and control points, which holds the outline (a
 * curve stays inside its control points). It is read once a glyph of a face; a text's box once a
 * TypesetText, which cannot change.
 */
final class InkBoxes
{
    private static ?self $shared = null;

    /** @var WeakMap<TypesetText, array{float, float, float, float}|null> */
    private WeakMap $texts;

    /** @var array<string, array{0: float, 1: array<int, array{float, float, float, float}|null>}> by source: 1 / units per em, and each glyph's box in font units, y up */
    private array $faces = [];

    /** @param  FontFaceDriver|null  $driver  What opens the faces; null is Typography's default face driver. */
    public function __construct(
        private ?FontFaceDriver $driver = null,
    ) {
        $this->texts = new WeakMap();
    }

    /** The one every recorder uses unless it is given its own: a glyph's box is the same for all. */
    public static function shared(): self
    {
        return self::$shared ??= new self();
    }

    /**
     * The box of the text's ink, [x0, y0, x1, y1]; null when it draws nothing (no glyph, or only
     * glyphs such as spaces, whose outlines are empty).
     *
     * @return array{float, float, float, float}|null
     *
     * @throws TypographyException When a run's source cannot be read.
     */
    public function of(TypesetText $text): ?array
    {
        if ($this->texts->offsetExists($text)) {
            return $this->texts[$text];
        }
        [$x0, $y0, $x1, $y1] = [INF, INF, -INF, -INF];
        foreach ($text->runs() as $run) {
            $k = $run->size * $this->face($run->source);
            $boxes = &$this->faces[$run->source->key()][1];
            foreach ($run->glyphs as $i => $glyph) {
                // a glyph with no ink is kept as null, and is still a glyph read
                $box = array_key_exists($glyph, $boxes) ? $boxes[$glyph] : ($boxes[$glyph] = $this->faces()->face($run->source)->outline($glyph)->bounds());
                if ($box === null) {
                    continue;
                }
                // font units are y up from the baseline; the text is y down
                $x0 = min($x0, $run->x[$i] + $k * $box[0]);
                $x1 = max($x1, $run->x[$i] + $k * $box[2]);
                $y0 = min($y0, $run->y[$i] - $k * $box[3]);
                $y1 = max($y1, $run->y[$i] - $k * $box[1]);
            }
            unset($boxes);
        }

        return $this->texts[$text] = $x0 === INF ? null : [$x0, $y0, $x1, $y1];
    }

    /** 1 / the source's units per em, read once a source. */
    private function face(FontSource $source): float
    {
        return ($this->faces[$source->key()] ??= [1.0 / $this->faces()->face($source)->unitsPerEm(), []])[0];
    }

    private function faces(): FontFaceDriver
    {
        return $this->driver ??= app('typography.faces')->driver();
    }
}
