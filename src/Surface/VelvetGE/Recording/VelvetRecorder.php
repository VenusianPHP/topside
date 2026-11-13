<?php

namespace Surface\VelvetGE\Recording;

use Surface\Contracts\Graphics\DrawingException;
use Surface\Contracts\Graphics\Effects\FilterChain;
use Surface\Contracts\Graphics\Effects\Glass;
use Surface\Contracts\Graphics\StrokeStyle;
use Surface\Contracts\Rendering\Framebuffers\DamageGranularity;
use Surface\Contracts\Rendering\Framebuffers\Filter;
use Surface\Contracts\Rendering\Framebuffers\Framebuffer;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Painting\Painter;
use Surface\Contracts\Rendering\Rasterization\FillRule;
use Surface\Contracts\Rendering\Recording\Queue;
use Surface\Contracts\Rendering\Recording\Recorder;
use Surface\Contracts\Rendering\Recording\ShapeQueue;
use Surface\Contracts\Typography\Font;
use Surface\Contracts\Typography\GFXFont;
use Surface\Contracts\Typography\ShapedGlyph;
use Surface\Contracts\Typography\TrueTypeFont;
use Surface\NutsAndBolts\Affine;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\Gradient;
use Surface\NutsAndBolts\Path;
use Surface\Rendering\Painters\Text\PlacedGlyph;
use Surface\Rendering\Painters\Text\Typesetter;
use Surface\Rendering\Rasterization\Stroker;

/**
 * VelvetGE's recorder: every draw call lowered to commands in framebuffer pixels, in PHP, over a
 * queue that holds them. It is complete over any queue. A queue that records the plain shapes
 * itself (a ShapeQueue) is handed those calls whole; paths, strokes, gradients, text, images,
 * layers and glass are lowered here whatever the queue.
 */
class VelvetRecorder implements Recorder
{
    /** A turned ellipse becomes a polygon that strays from it by less than this many pixels. */
    protected const float FLATNESS = 0.1;

    private const float LIMIT = Painter::LIMIT;

    /** The queue, when it records the plain shapes itself. */
    private ?ShapeQueue $shapes;

    private ?Region $surface = null;

    private ?Typesetter $typesetter = null;

    public function __construct(private Queue $queue)
    {
        $this->shapes = $queue instanceof ShapeQueue ? $queue : null;
    }

    public function begin(Region $surface): void
    {
        $this->surface = $surface;
        $this->queue->begin($surface);
    }

    public function drawing(): bool
    {
        return $this->queue->recording();
    }

    public function abandon(): void
    {
        $this->queue->abandon();
    }

    public function finish(): array
    {
        return $this->queue->finish();
    }

    public function push(): void
    {
        $this->open('push');
        $this->queue->push();
    }

    public function pop(): void
    {
        $this->open('pop');
        if ($this->queue->depth() === 0) {
            throw new DrawingException('pop() without a push().');
        }
        $this->queue->pop();
    }

    public function translate(float $x, float $y): void
    {
        $this->open('translate');
        self::finite(['x' => $x, 'y' => $y]);
        $this->queue->compose(Affine::translation($x, $y));
    }

    public function scale(float $x, ?float $y = null): void
    {
        $this->open('scale');
        self::finite(['x' => $x, 'y' => $y ?? $x]);
        $this->queue->compose(Affine::scaling($x, $y ?? $x));
    }

    public function rotate(float $radians): void
    {
        $this->open('rotate');
        self::finite(['radians' => $radians]);
        $this->queue->compose(Affine::rotation($radians));
    }

    public function transform(Affine $matrix): void
    {
        $this->open('transform');
        $this->queue->compose($matrix);
    }

    public function matrix(): Affine
    {
        return $this->queue->recording() ? $this->queue->matrix() : Affine::identity();
    }

    public function clip(?Region $region): void
    {
        $this->open('clip');
        $this->queue->clip(is_null($region) ? $this->surface : ($region->intersect($this->surface) ?? new Region(0, 0, 0, 0)));
    }

    public function clipRegion(): ?Region
    {
        return $this->queue->recording() ? $this->queue->clipRegion() : null;
    }

    public function clear(Color $color): void
    {
        if (! is_null($this->shapes)) {
            $this->shapes->clear(self::rgba($color));

            return;
        }
        $this->open('clear');
        $this->queue->append(['clear', self::rgba($color)]);
    }

    public function fillRect(float $x, float $y, float $width, float $height, Color $color): void
    {
        if (! is_null($this->shapes)) {
            $this->shapes->fillRect($x, $y, $width, $height, self::rgba($color));

            return;
        }
        $this->open('fillRect');
        self::finite(['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height]);
        if ($width <= 0 || $height <= 0) {
            return;
        }

        $this->path([$this->corners($x, $y, $width, $height)], FillRule::NON_ZERO, $color);
    }

    public function strokeRect(float $x, float $y, float $width, float $height, Color $color, float $stroke = 1.0): void
    {
        if (! is_null($this->shapes)) {
            $this->shapes->strokeRect($x, $y, $width, $height, self::rgba($color), $stroke);

            return;
        }
        $this->open('strokeRect');
        self::finite(['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height, 'stroke' => $stroke]);
        if ($width <= 0 || $height <= 0) {
            return;
        }

        $this->stroked($this->corners($x, $y, $width, $height), $stroke, true, $color);
    }

    public function line(float $x0, float $y0, float $x1, float $y1, Color $color, float $stroke = 1.0): void
    {
        if (! is_null($this->shapes)) {
            $this->shapes->line($x0, $y0, $x1, $y1, self::rgba($color), $stroke);

            return;
        }
        $this->open('line');
        self::finite(['x0' => $x0, 'y0' => $y0, 'x1' => $x1, 'y1' => $y1, 'stroke' => $stroke]);

        $this->stroked([$this->point($x0, $y0, 'x0', 'y0'), $this->point($x1, $y1, 'x1', 'y1')], $stroke, false, $color);
    }

    public function polyline(array $points, Color $color, float $stroke = 1.0, bool $closed = false): void
    {
        $this->open('polyline');
        self::finite(['stroke' => $stroke]);
        $points = $this->points($points);
        if ($points !== []) {
            $this->stroked($points, $stroke, $closed, $color);
        }
    }

    public function fillTriangle(float $x0, float $y0, float $x1, float $y1, float $x2, float $y2, Color $color): void
    {
        if (! is_null($this->shapes)) {
            $this->shapes->fillTriangle($x0, $y0, $x1, $y1, $x2, $y2, self::rgba($color));

            return;
        }
        $this->open('fillTriangle');
        self::finite(['x0' => $x0, 'y0' => $y0, 'x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2]);

        $this->path([[$this->point($x0, $y0, 'x0', 'y0'), $this->point($x1, $y1, 'x1', 'y1'), $this->point($x2, $y2, 'x2', 'y2')]], FillRule::NON_ZERO, $color);
    }

    public function fillPolygon(array $points, Color $color, FillRule $rule = FillRule::NON_ZERO): void
    {
        $this->open('fillPolygon');
        $points = $this->points($points);
        if (count($points) >= 3) {
            $this->path([$points], $rule, $color);
        }
    }

    public function fillPath(array $contours, Color $color, FillRule $rule = FillRule::NON_ZERO): void
    {
        $this->open('fillPath');
        $kept = [];
        foreach (array_values($contours) as $position => $contour) {
            if (! is_array($contour) || ! array_is_list($contour)) {
                throw new DrawingException("Contour {$position} is not a list of points.");
            }
            $points = $this->points($contour);
            if (count($points) >= 3) {
                $kept[] = $points;
            }
        }
        if ($kept !== []) {
            $this->path($kept, $rule, $color);
        }
    }

    public function fill(Path $path, Color|Gradient $paint, FillRule $rule = FillRule::NON_ZERO): void
    {
        $this->open('fill');
        $kept = [];
        foreach ($this->deviceSubpaths($path) as ['points' => $points]) {
            if (count($points) >= 3) {
                $kept[] = $points;
            }
        }
        if ($kept !== []) {
            $this->painted($kept, $rule, $paint);
        }
    }

    public function stroke(Path $path, Color|Gradient $paint, StrokeStyle $style): void
    {
        $this->open('stroke');
        $m = $this->queue->matrix();
        $squaredA = $m->a * $m->a + $m->b * $m->b;
        $squaredC = $m->c * $m->c + $m->d * $m->d;
        $scale = max($squaredA, $squaredC, 1.0);
        if (abs($squaredA - $squaredC) > 1e-9 * $scale || abs($m->a * $m->c + $m->b * $m->d) > 1e-9 * $scale) {
            throw DrawingException::unevenStroke();
        }
        $factor = sqrt($squaredA);
        $width = $this->strokeWidth($style->width);
        $contours = [];
        foreach ($this->deviceSubpaths($path) as ['points' => $points, 'closed' => $closed]) {
            $pieces = $style->dash === []
                ? [['points' => $points, 'closed' => $closed, 'direction' => null]]
                : Stroker::dashed($points, $closed, array_map(fn ($l) => $l * $factor, $style->dash), $style->dashOffset * $factor);
            foreach ($pieces as $piece) {
                foreach (Stroker::outline($piece['points'], $width, $piece['closed'], $style->cap, $style->join, $style->miterLimit, $piece['direction']) as $flat) {
                    $contours[] = array_chunk($flat, 2);
                }
            }
        }
        if ($contours !== [] && $width > 0) {
            $this->painted($contours, FillRule::NON_ZERO, $paint);
        }
    }

    public function fillEllipse(float $cx, float $cy, float $rx, float $ry, Color $color): void
    {
        if (! is_null($this->shapes)) {
            $this->shapes->fillEllipse($cx, $cy, $rx, $ry, self::rgba($color));

            return;
        }
        $this->open('fillEllipse');
        self::finite(['cx' => $cx, 'cy' => $cy, 'rx' => $rx, 'ry' => $ry]);
        if ($rx <= 0 || $ry <= 0) {
            return;
        }
        if (! $this->queue->matrix()->isAxisAligned()) {
            $this->path([$this->flatten($cx, $cy, $rx, $ry)], FillRule::NON_ZERO, $color);

            return;
        }

        [$x, $y, $radius_x, $radius_y] = $this->upright($cx, $cy, $rx, $ry);
        $clip = $this->queue->clipRegion();
        if ($radius_x > 0 && $radius_y > 0 && ! $clip->isEmpty()) {
            $this->queue->append(['ellipse', $x, $y, $radius_x, $radius_y, self::rgba($color), $clip]);
        }
    }

    public function strokeEllipse(float $cx, float $cy, float $rx, float $ry, Color $color, float $stroke = 1.0): void
    {
        if (! is_null($this->shapes)) {
            $this->shapes->strokeEllipse($cx, $cy, $rx, $ry, self::rgba($color), $stroke);

            return;
        }
        $this->open('strokeEllipse');
        self::finite(['cx' => $cx, 'cy' => $cy, 'rx' => $rx, 'ry' => $ry, 'stroke' => $stroke]);
        if ($rx <= 0 || $ry <= 0) {
            return;
        }
        if (! $this->queue->matrix()->isAxisAligned()) {
            $this->stroked($this->flatten($cx, $cy, $rx, $ry), $stroke, true, $color);

            return;
        }

        [$x, $y, $radius_x, $radius_y] = $this->upright($cx, $cy, $rx, $ry);
        $stroke = $this->strokeWidth($stroke);
        $clip = $this->queue->clipRegion();
        if ($radius_x > 0 && $radius_y > 0 && $stroke > 0 && ! $clip->isEmpty()) {
            $this->queue->append(['ring', $x, $y, $radius_x, $radius_y, $stroke, self::rgba($color), $clip]);
        }
    }

    public function image(Framebuffer $source, float $x, float $y, ?float $width = null, ?float $height = null, float $opacity = 1.0, Filter $filter = Filter::NEAREST, ?array $radii = null, ?Color $tint = null, ?array $sourceRect = null): void
    {
        $this->open('image');
        [$sx, $sy, $sw, $sh, $window] = self::sourceRect($sourceRect, (float) $source->viewportWidth(), (float) $source->viewportHeight());
        $width ??= abs($sw);
        $height ??= abs($sh);
        self::finite(['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height, 'opacity' => $opacity]);
        if ($opacity < 0.0 || $opacity > 1.0) {
            throw new DrawingException("The opacity is 0..1, got {$opacity}.");
        }
        $alpha = (int) round($opacity * 255);
        $tinted = is_null($tint) ? 0xFFFFFFFF : self::rgba($tint);
        $clip = $this->queue->clipRegion();
        if ($width <= 0 || $height <= 0 || $alpha === 0 || ($tinted & 0xFF) === 0 || $clip->isEmpty()) {
            return;
        }
        $radii = is_null($radii) ? null : self::heldRadii($radii, $width, $height);
        if (! is_null($radii) && ! $this->even()) {
            throw DrawingException::unevenRoundedImage();
        }

        // Its four corners, to hold the placed image to the same limit as every other shape.
        $this->corners($x, $y, $width, $height);
        $m = $this->queue->matrix();
        // the destination box takes the source rect: its left edge shows $sx, its right edge $sx + $sw (a negative size mirrors)
        $placement = $m
            ->multiply(Affine::translation($x, $y))
            ->multiply(Affine::scaling($width / $sw, $height / $sh))
            ->multiply(Affine::translation(-$sx, -$sy));
        if (is_null($placement->inverse())) {
            return;
        }
        $round = null;
        if (! is_null($radii)) {
            $s = $m->a;
            [$x0, $y0] = $this->point($x, $y);
            [$x1, $y1] = $this->point($x + $width, $y + $height);
            $round = [$x0, $y0, $x1, $y1, [$radii[0] * $s, $radii[1] * $s, $radii[2] * $s, $radii[3] * $s]];
        }
        $style = $tinted === 0xFFFFFFFF && is_null($window) && is_null($round) ? null : ['tint' => $tinted, 'window' => $window, 'round' => $round];
        $this->queue->append(['image', $source, $placement, $alpha, $filter, $style, $clip]);
    }

    public function roundRect(float $x, float $y, float $width, float $height, array $radii, Color $fill, ?Color $border = null, float $borderWidth = 0.0): void
    {
        if (! is_null($this->shapes)) {
            $this->shapes->roundRect($x, $y, $width, $height, $radii, self::rgba($fill), is_null($border) ? null : self::rgba($border), $borderWidth);

            return;
        }
        $this->open('roundRect');
        self::finite(['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height, 'borderWidth' => $borderWidth]);
        $radii = self::heldRadii($radii, $width, $height);
        if ($width <= 0 || $height <= 0) {
            return;
        }
        $bw = is_null($border) ? 0.0 : max(0.0, $borderWidth);
        $inner = [max(0.0, $radii[0] - $bw), max(0.0, $radii[1] - $bw), max(0.0, $radii[2] - $bw), max(0.0, $radii[3] - $bw)];
        $hollow = $width > 2 * $bw && $height > 2 * $bw;

        if (! $this->even()) {
            // shortcut: a turned rounded rect is a polygon path, corners within FLATNESS, as a turned ellipse is.
            $outer = $this->roundedOutline($x, $y, $width, $height, $radii);
            if ($bw <= 0) {
                $this->path([$outer], FillRule::NON_ZERO, $fill);

                return;
            }
            $hole = $hollow ? $this->roundedOutline($x + $bw, $y + $bw, $width - 2 * $bw, $height - 2 * $bw, $inner) : null;
            if (! is_null($hole)) {
                $this->path([$hole], FillRule::NON_ZERO, $fill);
            }
            $this->path(is_null($hole) ? [$outer] : [$outer, $hole], FillRule::EVEN_ODD, $border);

            return;
        }

        $s = $this->queue->matrix()->a;
        [$x0, $y0] = $this->point($x, $y);
        [$x1, $y1] = $this->point($x + $width, $y + $height);
        $clip = $this->queue->clipRegion();
        if ($clip->isEmpty()) {
            return;
        }
        $scaled = static fn (array $r): array => [$r[0] * $s, $r[1] * $s, $r[2] * $s, $r[3] * $s];
        if ($bw <= 0) {
            $this->queue->append(['roundrect', $x0, $y0, $x1, $y1, $scaled($radii), self::rgba($fill), $clip]);

            return;
        }
        $inset = $bw * $s;
        if ($hollow) {
            $this->queue->append(['roundrect', $x0 + $inset, $y0 + $inset, $x1 - $inset, $y1 - $inset, $scaled($inner), self::rgba($fill), $clip]);
        }
        $this->queue->append(['roundring', $x0, $y0, $x1, $y1, $scaled($radii), $inset, self::rgba($border), $clip]);
    }

    public function shadow(float $x, float $y, float $width, float $height, float $radius, float $blur, Color $color, float $dx = 0.0, float $dy = 0.0): void
    {
        if (! is_null($this->shapes)) {
            $this->shapes->shadow($x, $y, $width, $height, $radius, $blur, self::rgba($color), $dx, $dy);

            return;
        }
        $this->open('shadow');
        self::finite(['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height, 'radius' => $radius, 'blur' => $blur, 'dx' => $dx, 'dy' => $dy]);
        if ($width <= 0 || $height <= 0) {
            return;
        }
        if (! $this->even()) {
            // shortcut: a blurred turned box needs its distance in the box's own frame; refused until the shape gains it.
            throw DrawingException::unevenShadow();
        }

        $s = $this->queue->matrix()->a;
        $blur = max(0.0, $blur);
        [$x0, $y0] = $this->point($x + $dx, $y + $dy);
        [$x1, $y1] = $this->point($x + $width + $dx, $y + $height + $dy);
        $radius = max(0.0, min($radius, min($width, $height) / 2));
        $clip = $this->queue->clipRegion();
        if (! $clip->isEmpty()) {
            $this->queue->append(['shadow', $x0, $y0, $x1, $y1, $radius * $s, $blur * $s, self::rgba($color), $clip]);
        }
    }

    public function layer(float $x, float $y, float $width, float $height, callable $draw, float $opacity = 1.0, ?array $radii = null, ?FilterChain $filter = null, ?FilterChain $backdrop = null): void
    {
        $this->open('layer');
        self::finite(['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height, 'opacity' => $opacity]);
        if ($opacity < 0.0 || $opacity > 1.0) {
            throw new DrawingException("The opacity is 0..1, got {$opacity}.");
        }
        $alpha = (int) round($opacity * 255);
        $clip = $this->queue->clipRegion();
        if ($width <= 0 || $height <= 0 || $clip->isEmpty() || ($alpha === 0 && is_null($backdrop))) {
            return;
        }
        if (! $this->even()) {
            throw DrawingException::unevenLayer();
        }
        $radii = is_null($radii) ? [0.0, 0.0, 0.0, 0.0] : self::heldRadii($radii, $width, $height);
        $s = $this->queue->matrix()->a;
        [$x0, $y0] = $this->point($x, $y);
        [$x1, $y1] = $this->point($x + $width, $y + $height);
        $edge = new Region((int) floor($x0), (int) floor($y0), (int) ceil($x1) - (int) floor($x0), (int) ceil($y1) - (int) floor($y0));
        $area = $clip->intersect($edge);
        if (is_null($area)) {
            return;
        }

        // the children are recorded in a list of their own, clipped to the layer
        $this->queue->enter($area);
        try {
            $draw();
            if ($this->queue->depth() !== 0) {
                throw DrawingException::badLayer('the drawing left a push() without its pop().');
            }
        } finally {
            $children = $this->queue->leave();
        }
        if ($children === [] && is_null($filter) && is_null($backdrop)) {
            return;
        }
        $geometry = [$x0, $y0, $x1, $y1, [$radii[0] * $s, $radii[1] * $s, $radii[2] * $s, $radii[3] * $s]];
        $this->queue->append(['layer', $area, $geometry, $children, $alpha, $filter, $backdrop, $area]);
    }

    /** Upright and even: no turn or shear, x and y scaled alike, by more than 0. */
    public function glass(float $x, float $y, float $width, float $height, ?array $radii, Glass $glass, float $opacity = 1.0): void
    {
        $this->open('glass');
        self::finite(['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height, 'opacity' => $opacity]);
        if ($opacity < 0.0 || $opacity > 1.0) {
            throw new DrawingException("The opacity is 0..1, got {$opacity}.");
        }
        $alpha = (int) round($opacity * 255);
        $clip = $this->queue->clipRegion();
        if ($width <= 0 || $height <= 0 || $alpha === 0 || $clip->isEmpty()) {
            return;
        }
        if (! $this->even()) {
            throw new DrawingException('glass() needs an upright transform that scales x and y alike.');
        }
        $radii = is_null($radii) ? [0.0, 0.0, 0.0, 0.0] : self::heldRadii($radii, $width, $height);
        $s = $this->queue->matrix()->a;
        [$x0, $y0] = $this->point($x, $y);
        [$x1, $y1] = $this->point($x + $width, $y + $height);
        $area = $clip->intersect(new Region((int) floor($x0), (int) floor($y0), (int) ceil($x1) - (int) floor($x0), (int) ceil($y1) - (int) floor($y0)));
        if (is_null($area)) {
            return;
        }
        // a blur under a pixel and a half has nothing to blur: the pane shows the sharp capture (the old blur step was skipped below 0.75 px too)
        $frost = $glass->blur * $s < 0.75 ? 0.0 : $glass->frost;
        $look = [$frost, $glass->refraction * $s, $glass->bevel * $s, $glass->saturation, $glass->light, $glass->grain,
            is_null($glass->tint) ? 0 : self::rgba($glass->tint), is_null($glass->rim) ? 0 : self::rgba($glass->rim), $alpha];
        $device = [$radii[0] * $s, $radii[1] * $s, $radii[2] * $s, $radii[3] * $s];
        $shadow = $glass->shadow > 0 && ! is_null($glass->shadowColor)
            ? ['shadow', $x0, $y0, $x1, $y1, $device[0], $glass->shadow * $s, self::rgba($glass->shadowColor), $clip]
            : null;
        $this->queue->append(['glass', $area, [$x0, $y0, $x1, $y1, $device], $look, $glass->blur * $s, $shadow, $area]);
    }

    public function text(string $text, float $x, float $y, Color $color, Font $font): void
    {
        $this->open('text');
        self::finite(['x' => $x, 'y' => $y]);

        match (true) {
            $font instanceof GFXFont => $this->bitmapText($text, $x, $y, $color, $font),
            $font instanceof TrueTypeFont => $this->trueTypeText($text, $x, $y, $color, $font),
            default => throw DrawingException::unknownFont($font::class),
        };
    }

    public function textBounds(string $text, Font $font): array
    {
        if ($font instanceof TrueTypeFont) {
            $box = self::placeGlyphs($font, $font->size, $text, 0.0, 0.0)[1];

            return is_null($box) ? [0.0, 0.0, 0.0, 0.0] : [(float) $box->x, (float) $box->y, (float) $box->width, (float) $box->height];
        }
        if (! $font instanceof GFXFont) {
            throw DrawingException::unknownFont($font::class);
        }

        return array_map(floatval(...), ($this->typesetter ??= new Typesetter)->bounds($font, $text));
    }

    public function drawn(Region $surface, DamageGranularity $granularity, array $also = []): array
    {
        return $this->queue->drawn($surface, $granularity, $also);
    }

    public function changed(Region $surface, DamageGranularity $granularity, array $also = []): array
    {
        return $this->queue->changed($surface, $granularity, $also);
    }

    public function scissor(array $commands, array $regions): array
    {
        return Boxes::scissor($commands, $regions);
    }

    public function box(array $command, Region $surface): ?Region
    {
        return Boxes::box($command, $surface);
    }

    /** Contours already through the transform, in a colour or a gradient. */
    private function painted(array $contours, FillRule $rule, Color|Gradient $paint): void
    {
        if ($paint instanceof Color) {
            $this->path($contours, $rule, $paint);

            return;
        }
        $inverse = $paint->toGradientSpace($this->queue->matrix());
        $clip = $this->queue->clipRegion();
        if (! is_null($inverse) && ! $clip->isEmpty()) {
            $this->queue->append(['gradpath', $contours, $rule, ['kind' => $paint->kind(), 'inverse' => $inverse, 'spread' => $paint->spread, 'ramp' => $paint->ramp()], $clip]);
        }
    }

    /**
     * The path through the transform, checked against the limit, flattened within a quarter pixel.
     *
     * @return list<array{points: list<array{float, float}>, closed: bool}>
     */
    private function deviceSubpaths(Path $path): array
    {
        $m = $this->queue->matrix();
        $device = $m->a === 1.0 && $m->b === 0.0 && $m->c === 0.0 && $m->d === 1.0 && $m->e === 0.0 && $m->f === 0.0 ? $path : $path->transformed($m);
        foreach ($device->ops() as $op) {
            if (count($op) < 2) {
                continue;
            }
            // one pass a segment in C; a coordinate that fails it is found by the slow loop, which names it
            $numbers = array_slice($op, 1);
            if (is_finite(array_sum($numbers)) && max($numbers) <= self::LIMIT && min($numbers) >= -self::LIMIT) {
                continue;
            }
            for ($i = 1; $i < count($op); $i++) {
                if (! is_finite($op[$i]) || abs($op[$i]) > self::LIMIT) {
                    throw DrawingException::pastLimit('path', (float) $op[$i]);
                }
            }
        }

        return $device->flatten(0.25);
    }

    /**
     * The source rect [x, y, w, h] in source pixels: a negative w or h mirrors that axis (the destination's
     * left or top edge shows x or y, its right or bottom edge x + w or y + h). It must be a non-empty rect
     * inside the source. Null is the whole source.
     *
     * @param  array<int, mixed>|null  $rect
     * @return array{float, float, float, float, array{float, float, float, float}|null} x, y, w, h, and the painted window [u0, v0, u1, v1]; the window is null for the whole source
     *
     * @throws DrawingException When the rect is not four finite numbers, is empty, or reaches outside the source.
     */
    private static function sourceRect(?array $rect, float $width, float $height): array
    {
        if (is_null($rect)) {
            return [0.0, 0.0, $width, $height, null];
        }
        if (! array_is_list($rect) || count($rect) !== 4) {
            throw new DrawingException('A source rect is a list of four numbers: [x, y, w, h].');
        }
        [$x, $y, $w, $h] = array_map(fn (mixed $n): float => is_int($n) || is_float($n) ? (float) $n : throw new DrawingException('A source rect is a list of four numbers: [x, y, w, h].'), $rect);
        self::finite(['source x' => $x, 'source y' => $y, 'source w' => $w, 'source h' => $h]);
        [$u0, $u1] = [min($x, $x + $w), max($x, $x + $w)];
        [$v0, $v1] = [min($y, $y + $h), max($y, $y + $h)];
        if ($w === 0.0 || $h === 0.0 || $u0 < 0.0 || $v0 < 0.0 || $u1 > $width || $v1 > $height) {
            throw new DrawingException("The source rect [{$x}, {$y}, {$w}, {$h}] is empty or outside the {$width} x {$height} source.");
        }

        return [$x, $y, $w, $h, [$u0, $v0, $u1, $v1]];
    }

    private function even(): bool
    {
        $m = $this->queue->matrix();

        return $m->b === 0.0 && $m->c === 0.0 && $m->a === $m->d && $m->a > 0.0;
    }

    /**
     * Four radii, each held to 0..min(w, h)/2.
     *
     * @return array{float, float, float, float}
     */
    private static function heldRadii(array $radii, float $width, float $height): array
    {
        if (! array_is_list($radii) || count($radii) !== 4) {
            throw DrawingException::badRadii();
        }
        $half = min($width, $height) / 2;
        $held = [];
        foreach ($radii as $i => $r) {
            if (! is_int($r) && ! is_float($r)) {
                throw DrawingException::badRadii();
            }
            self::finite(["radius {$i}" => (float) $r]);
            $held[] = max(0.0, min((float) $r, $half));
        }

        return $held;
    }

    /**
     * A rounded rect's outline through the transform, clockwise from the top-left corner; each
     * corner a quarter of the sides flatten() gives its circle.
     *
     * @param  array{float, float, float, float}  $radii
     * @return list<array{float, float}>
     */
    private function roundedOutline(float $x, float $y, float $width, float $height, array $radii): array
    {
        $m = $this->queue->matrix();
        $stretch = sqrt(max($m->a * $m->a + $m->b * $m->b, $m->c * $m->c + $m->d * $m->d));
        $corners = [
            [$x + $radii[0], $y + $radii[0], $radii[0], M_PI],
            [$x + $width - $radii[1], $y + $radii[1], $radii[1], 1.5 * M_PI],
            [$x + $width - $radii[2], $y + $height - $radii[2], $radii[2], 0.0],
            [$x + $radii[3], $y + $height - $radii[3], $radii[3], 0.5 * M_PI],
        ];
        $points = [];
        foreach ($corners as [$cx, $cy, $r, $from]) {
            if ($r <= 0) {
                $points[] = $this->point($cx, $cy);

                continue;
            }
            $sides = (int) max(12, min(1024, ceil(M_PI * sqrt($r * $stretch / (2 * self::FLATNESS)) * 1.01)));
            $steps = (int) ceil($sides / 4);
            for ($i = 0; $i <= $steps; $i++) {
                $angle = $from + 0.5 * M_PI * $i / $steps;
                $points[] = $this->point($cx + $r * cos($angle), $cy + $r * sin($angle));
            }
        }

        return $points;
    }

    private function bitmapText(string $text, float $x, float $y, Color $color, GFXFont $font): void
    {
        $typesetter = $this->typesetter ??= new Typesetter;
        $placed_glyphs = $typesetter->layout($font, $text);

        // Upright, at a whole-number scale, on whole pixels, in an opaque
        // colour: every glyph pixel is a block of whole framebuffer pixels,
        // so the spans are written here and nothing is rasterised.
        $m = $this->queue->matrix();
        [$left, $top] = $this->point($x, $y);
        if ($m->b === 0.0 && $m->c === 0.0 && $m->a >= 1.0 && $m->d >= 1.0 && $color->alpha >= 1.0
            && floor($m->a) === $m->a && floor($m->d) === $m->d && floor($left) === $left && floor($top) === $top) {
            $this->blocks($placed_glyphs, $font, (int) $left, (int) $top, (int) $m->a, (int) $m->d, $color);

            return;
        }

        // Otherwise every run of inked pixels in every glyph is one rectangle
        // of one path, so a text is rasterised once, under the transform.
        $contours = [];
        foreach ($placed_glyphs as $placed) {
            foreach ($typesetter->runs($font, $placed->glyph) as [$row, $first, $last]) {
                $contours[] = $this->corners($x + $placed->x + $first, $y + $placed->y + $row, $last - $first + 1, 1);
            }
        }
        if ($contours !== []) {
            $this->path($contours, FillRule::NON_ZERO, $color);
        }
    }

    /**
     * @param  list<PlacedGlyph>  $placed_glyphs
     */
    private function blocks(array $placed_glyphs, GFXFont $font, int $left, int $top, int $sx, int $sy, Color $color): void
    {
        $clip = $this->queue->clipRegion();
        $clip_right = $clip->x + $clip->width;
        $clip_bottom = $clip->y + $clip->height;
        /** @var array<int, list<string>> $rows spans by row, so the list runs top to bottom */
        $rows = [];

        foreach ($placed_glyphs as $placed) {
            foreach ($this->typesetter->runs($font, $placed->glyph) as [$row, $first, $last]) {
                $x0 = max($left + ($placed->x + $first) * $sx, $clip->x);
                $x1 = min($left + ($placed->x + $last + 1) * $sx, $clip_right);
                $y0 = max($top + ($placed->y + $row) * $sy, $clip->y);
                $y1 = min($top + ($placed->y + $row + 1) * $sy, $clip_bottom);
                if ($x1 <= $x0) {
                    continue;
                }
                for ($line = $y0; $line < $y1; $line++) {
                    $rows[$line][] = pack('vvvC', $line, $x0, $x1 - $x0, 255);
                }
            }
        }

        if ($rows !== []) {
            ksort($rows);
            $this->queue->append(['spans', implode('', array_merge(...$rows)), self::rgba($color), $clip]);
        }
    }

    /**
     * Upright at an even scale, one 'glyphs' command: the font shaped at its size times the scale, each
     * glyph origin on a whole pixel. Otherwise one path of every glyph's outline, through the transform.
     */
    private function trueTypeText(string $text, float $x, float $y, Color $color, TrueTypeFont $font): void
    {
        $m = $this->queue->matrix();
        if ($m->b !== 0.0 || $m->c !== 0.0 || $m->a !== $m->d || $m->a <= 0.0) {
            $contours = [];
            foreach (self::trueTypeLayout($font, $font->size, $text) as [$g, $baseline]) {
                foreach ($font->outline($g->face, $font->size, $g->glyph, $g->style) as $contour) {
                    $contours[] = $this->points(array_map(fn (array $p): array => [$x + $g->x + $p[0], $y + $baseline + $g->y + $p[1]], $contour));
                }
            }
            if ($contours !== []) {
                $this->path($contours, FillRule::NON_ZERO, $color);
            }

            return;
        }

        $pixelSize = $font->size * $m->a;
        [$left, $top] = $this->point($x, $y);
        [$glyphs, $box] = self::placeGlyphs($font, $pixelSize, $text, $left, $top);
        $clip = $this->queue->clipRegion();
        if ($glyphs !== [] && ! $clip->isEmpty()) {
            $this->queue->append(['glyphs', $font, $pixelSize, $glyphs, self::rgba($color), $box, $clip]);
        }
    }

    /**
     * shortcut: glyph origins are rounded to whole pixels (no subpixel positioning); upgrade when text must sit between pixels.
     *
     * @return array{list<array{string, int, int, int, int}>, ?Region} the glyphs with ink, and the union of their masks
     */
    private static function placeGlyphs(TrueTypeFont $font, float $pixelSize, string $text, float $left, float $top): array
    {
        $glyphs = [];
        $box = null;
        foreach (self::trueTypeLayout($font, $pixelSize, $text) as [$g, $baseline]) {
            $mask = $font->mask($g->face, $pixelSize, $g->glyph, $g->style);
            if ($mask->width === 0 || $mask->height === 0) {
                continue;
            }
            $mx = (int) round($left + $g->x) + $mask->left;
            $my = (int) round($top + $baseline + $g->y) - $mask->top;
            $glyphs[] = [$g->face, $g->glyph, $mx, $my, $g->style];
            $placed = new Region($mx, $my, $mask->width, $mask->height);
            $box = $box?->union($placed) ?? $placed;
        }

        return [$glyphs, $box];
    }

    /**
     * Lines split on "\n" with "\r" removed; each line's baseline is its top plus its ascent.
     *
     * @return list<array{ShapedGlyph, float}> each glyph with its line's baseline, from the text's top
     */
    private static function trueTypeLayout(TrueTypeFont $font, float $pixelSize, string $text): array
    {
        $out = [];
        $lineTop = 0.0;
        foreach (explode("\n", str_replace("\r", '', $text)) as $line) {
            if ($line === '') {
                $lineTop += $font->shape($pixelSize, ' ')->lineHeight;

                continue;
            }
            $run = $font->shape($pixelSize, $line);
            foreach ($run->glyphs as $g) {
                $out[] = [$g, $lineTop + $run->ascent];
            }
            $lineTop += $run->lineHeight;
        }

        return $out;
    }

    /** @throws DrawingException When no frame is open. */
    private function open(string $call): void
    {
        if (! $this->queue->recording()) {
            throw DrawingException::outsideFrame($call);
        }
    }

    /** @param list<list<array{float, float}>> $contours Already transformed. */
    private function path(array $contours, FillRule $rule, Color $color): void
    {
        $clip = $this->queue->clipRegion();
        if (! $clip->isEmpty()) {
            $this->queue->append(['path', $contours, $rule, self::rgba($color), $clip]);
        }
    }

    /** @param list<array{float, float}> $points Already transformed. */
    private function stroked(array $points, float $stroke, bool $closed, Color $color): void
    {
        $stroke = $this->strokeWidth($stroke);
        $clip = $this->queue->clipRegion();
        if ($stroke > 0 && ! $clip->isEmpty()) {
            $this->queue->append(['polyline', $points, $stroke, $closed, self::rgba($color), $clip]);
        }
    }

    /** A stroke as wide as the transform makes it: scaled by the square root of the area it scales by. */
    private function strokeWidth(float $stroke): float
    {
        $scaled = $stroke * sqrt(abs($this->queue->matrix()->determinant()));
        if (abs($scaled) > self::LIMIT) {
            throw DrawingException::pastLimit('stroke', $scaled);
        }

        return $scaled;
    }

    /** @return array{float, float} A point through the transform, held to the limit. */
    private function point(float $x, float $y, string $x_name = 'x', string $y_name = 'y'): array
    {
        [$tx, $ty] = $this->queue->matrix()->apply($x, $y);
        foreach ([$x_name => $tx, $y_name => $ty] as $name => $value) {
            if (! is_finite($value) || abs($value) > self::LIMIT) {
                throw DrawingException::pastLimit($name, $value);
            }
        }

        return [$tx, $ty];
    }

    /** @return list<array{float, float}> A rect's corners through the transform: top-left, top-right, bottom-right, bottom-left. */
    private function corners(float $x, float $y, float $width, float $height): array
    {
        return [$this->point($x, $y), $this->point($x + $width, $y), $this->point($x + $width, $y + $height), $this->point($x, $y + $height)];
    }

    /**
     * [x, y] points, each checked, through the transform.
     *
     * @return list<array{float, float}>
     */
    private function points(array $points): array
    {
        $out = [];
        foreach (array_values($points) as $position => $point) {
            if (! is_array($point) || ! array_is_list($point) || count($point) !== 2
                || ! (is_int($point[0]) || is_float($point[0])) || ! (is_int($point[1]) || is_float($point[1]))) {
                throw DrawingException::notAPoint($position);
            }
            self::finite(["Point {$position} x" => (float) $point[0], "Point {$position} y" => (float) $point[1]]);
            $out[] = $this->point((float) $point[0], (float) $point[1], "Point {$position} x", "Point {$position} y");
        }

        return $out;
    }

    /**
     * An ellipse under a transform that keeps its axes: its centre and radii in framebuffer pixels.
     *
     * @return array{float, float, float, float}
     */
    private function upright(float $cx, float $cy, float $rx, float $ry): array
    {
        $m = $this->queue->matrix();
        [$x, $y] = $this->point($cx, $cy, 'cx', 'cy');
        $radius_x = $rx * abs($m->a);
        $radius_y = $ry * abs($m->d);
        foreach (['rx' => $radius_x, 'ry' => $radius_y] as $name => $radius) {
            if ($radius > self::LIMIT) {
                throw DrawingException::pastLimit($name, $radius);
            }
        }

        return [$x, $y, $radius_x, $radius_y];
    }

    /**
     * An ellipse as a polygon through the transform, for transforms that turn or shear it. A polygon of n sides strays
     * from a circle of radius r by r·(1 − cos(π / n)), about r·π² / 2n², so n = π·sqrt(r / 2·FLATNESS) sides keep it within FLATNESS.
     *
     * @return list<array{float, float}>
     */
    private function flatten(float $cx, float $cy, float $rx, float $ry): array
    {
        $m = $this->queue->matrix();
        $radius = max($rx, $ry) * sqrt(max($m->a * $m->a + $m->b * $m->b, $m->c * $m->c + $m->d * $m->d));
        $sides = (int) max(12, min(1024, ceil(M_PI * sqrt($radius / (2 * self::FLATNESS)) * 1.01)));

        $points = [];
        for ($i = 0; $i < $sides; $i++) {
            $angle = 2 * M_PI * $i / $sides;
            $points[] = $this->point($cx + $rx * cos($angle), $cy + $ry * sin($angle), 'cx', 'cy');
        }

        return $points;
    }

    /** @param array<string, float> $numbers */
    private static function finite(array $numbers): void
    {
        foreach ($numbers as $name => $value) {
            if (! is_finite($value)) {
                throw DrawingException::notFinite($name);
            }
        }
    }

    private static function rgba(Color $color): int
    {
        return ((int) round($color->red * 255) << 24) | ((int) round($color->green * 255) << 16) | ((int) round($color->blue * 255) << 8) | (int) round($color->alpha * 255);
    }
}
