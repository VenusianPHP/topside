<?php

namespace Surface\Rendering\Painters;

use Surface\Contracts\Graphics\DrawingException;
use Surface\Contracts\Graphics\Effects\FilterChain;
use Surface\Contracts\Graphics\Effects\Glass;
use Surface\Contracts\Graphics\StrokeStyle;
use Surface\Contracts\Rendering\Framebuffers\Filter;
use Surface\Contracts\Rendering\Framebuffers\Framebuffer;
use Surface\Contracts\Rendering\Framebuffers\PagedFramebuffer;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Framebuffers\RingFramebuffer;
use Surface\Contracts\Rendering\Painting\Painter as RenderingEngineContract;
use Surface\Contracts\Rendering\Rasterization\FillRule;
use Surface\Contracts\Rendering\Recording\Recorder;
use Surface\Contracts\Typography\Font;
use Surface\NutsAndBolts\Affine;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\Gradient;
use Surface\NutsAndBolts\Path;
use Surface\Rendering\Recorders;
use Throwable;

/**
 * What every painter shares: frames, and what a frame's end draws. The draw and state calls go to
 * a recorder (Surface\Contracts\Rendering\Recording\Recorder lists the commands it lowers them
 * to); an engine says what it is called, where it draws, and how it carries out a frame's commands
 * (execute). Everything a caller touches is written here once.
 */
abstract class Painter implements RenderingEngineContract
{
    /** What records this painter's frames; made on first use when the engine gave none. */
    private ?Recorder $recorder = null;

    /** @var list<array<int, mixed>>|null The last ended frame. */
    private ?array $last = null;

    /** The next frame is drawn and reported whole. */
    private bool $whole = true;

    /** @var list<Region> Where the last ended frame changed the framebuffer. */
    private array $damage = [];

    /** @var list<Region> scrolled since the last end(): the framebuffer no longer holds the last frame there */
    private array $scrolled = [];

    /**
     * Carry out one frame.
     *
     * @param  list<array<int, mixed>>  $commands
     */
    abstract protected function execute(array $commands): void;

    /** Record this painter's frames with $recorder: an engine calls it as it is made, before any frame. */
    protected function recordWith(Recorder $recorder): void
    {
        $this->recorder = $recorder;
    }

    /** The recorder the engine gave, or one of Rendering's default recording driver. */
    protected function recorder(): Recorder
    {
        return $this->recorder ??= Recorders::driver()->recorder();
    }

    public function width(): int
    {
        return $this->framebuffer()->viewportWidth();
    }

    public function height(): int
    {
        return $this->framebuffer()->viewportHeight();
    }

    public function begin(): static
    {
        if ($this->drawing()) {
            throw new DrawingException('A frame is already open: end() it before begin().');
        }
        $this->recorder()->begin($this->surface());

        return $this;
    }

    public function end(): static
    {
        $recorder = $this->recorder();
        if (! $recorder->drawing()) {
            throw DrawingException::outsideFrame('end');
        }
        $commands = $recorder->finish();
        $compared = ! $this->whole && ! is_null($this->last);
        $this->last = $commands;
        $this->whole = false;

        $scrolled = $this->scrolled;
        $this->scrolled = [];

        $framebuffer = $this->framebuffer();
        if (! $compared || $framebuffer instanceof PagedFramebuffer) {
            $this->damage = [$this->surface()];
            $this->execute($commands);

            return $this;
        }

        // A frame that clears starts from nothing, so outside what changed it
        // repaints the pixels already there. One that does not draws over
        // them: all of it is drawn.
        if (($commands[0][0] ?? null) !== 'clear' || isset($commands[0][2])) {
            $this->damage = $recorder->drawn($this->surface(), $framebuffer->damageGranularity(), $scrolled);
            $this->execute($commands);

            return $this;
        }

        // A scrolled region no longer holds what the last frame drew there: it is drawn again.
        $this->damage = $recorder->changed($this->surface(), $framebuffer->damageGranularity(), $scrolled);
        if ($this->damage !== []) {
            $this->execute($this->keepsFrame($framebuffer) ? $recorder->scissor($commands, $this->damage) : $commands);
        }

        return $this;
    }

    public function canScroll(): bool
    {
        return $this->keepsFrame($this->framebuffer());
    }

    public function scroll(Region $region, int $dx, int $dy): static
    {
        if ($this->drawing()) {
            throw new DrawingException('A frame is open: scroll() runs between frames.');
        }
        if (! $this->canScroll()) {
            throw new DrawingException('This framebuffer does not keep its frame: it cannot scroll.');
        }
        $this->framebuffer()->scroll($region, $dx, $dy);
        $clip = $region->intersect($this->surface());
        if (! is_null($clip) && ($dx !== 0 || $dy !== 0)) {
            $this->scrolled[] = $clip;
        }

        return $this;
    }

    public function damage(): array
    {
        return $this->damage;
    }

    public function invalidate(): static
    {
        $this->whole = true;

        return $this;
    }

    public function frame(callable $draw): static
    {
        $this->begin();
        try {
            $draw($this);
        } catch (Throwable $e) {
            $this->recorder()->abandon();

            throw $e;
        }

        return $this->end();
    }

    public function replay(): static
    {
        if ($this->drawing()) {
            throw new DrawingException('replay() comes after end(): a frame is open.');
        }
        if (is_null($this->last)) {
            throw new DrawingException('There is no frame to replay: none has ended yet.');
        }
        $this->execute($this->last);

        return $this;
    }

    public function drawing(): bool
    {
        return $this->recorder()->drawing();
    }

    public function push(): static
    {
        $this->recorder()->push();

        return $this;
    }

    public function pop(): static
    {
        $this->recorder()->pop();

        return $this;
    }

    public function translate(float $x, float $y): static
    {
        $this->recorder()->translate($x, $y);

        return $this;
    }

    public function scale(float $x, ?float $y = null): static
    {
        $this->recorder()->scale($x, $y);

        return $this;
    }

    public function rotate(float $radians): static
    {
        $this->recorder()->rotate($radians);

        return $this;
    }

    public function transform(Affine $matrix): static
    {
        $this->recorder()->transform($matrix);

        return $this;
    }

    public function matrix(): Affine
    {
        return $this->recorder()->matrix();
    }

    public function clip(?Region $region): static
    {
        $this->recorder()->clip($region);

        return $this;
    }

    public function clipRegion(): Region
    {
        return $this->recorder()->clipRegion() ?? $this->surface();
    }

    public function clear(Color $color): static
    {
        $this->recorder()->clear($color);

        return $this;
    }

    public function fillRect(float $x, float $y, float $width, float $height, Color $color): static
    {
        $this->recorder()->fillRect($x, $y, $width, $height, $color);

        return $this;
    }

    public function strokeRect(float $x, float $y, float $width, float $height, Color $color, float $stroke = 1.0): static
    {
        $this->recorder()->strokeRect($x, $y, $width, $height, $color, $stroke);

        return $this;
    }

    public function line(float $x0, float $y0, float $x1, float $y1, Color $color, float $stroke = 1.0): static
    {
        $this->recorder()->line($x0, $y0, $x1, $y1, $color, $stroke);

        return $this;
    }

    public function polyline(array $points, Color $color, float $stroke = 1.0, bool $closed = false): static
    {
        $this->recorder()->polyline($points, $color, $stroke, $closed);

        return $this;
    }

    public function fillTriangle(float $x0, float $y0, float $x1, float $y1, float $x2, float $y2, Color $color): static
    {
        $this->recorder()->fillTriangle($x0, $y0, $x1, $y1, $x2, $y2, $color);

        return $this;
    }

    public function fillPolygon(array $points, Color $color, FillRule $rule = FillRule::NON_ZERO): static
    {
        $this->recorder()->fillPolygon($points, $color, $rule);

        return $this;
    }

    public function fillPath(array $contours, Color $color, FillRule $rule = FillRule::NON_ZERO): static
    {
        $this->recorder()->fillPath($contours, $color, $rule);

        return $this;
    }

    public function fill(Path $path, Color|Gradient $paint, FillRule $rule = FillRule::NON_ZERO): static
    {
        $this->recorder()->fill($path, $paint, $rule);

        return $this;
    }

    public function stroke(Path $path, Color|Gradient $paint, StrokeStyle $style): static
    {
        $this->recorder()->stroke($path, $paint, $style);

        return $this;
    }

    public function fillEllipse(float $cx, float $cy, float $rx, float $ry, Color $color): static
    {
        $this->recorder()->fillEllipse($cx, $cy, $rx, $ry, $color);

        return $this;
    }

    public function strokeEllipse(float $cx, float $cy, float $rx, float $ry, Color $color, float $stroke = 1.0): static
    {
        $this->recorder()->strokeEllipse($cx, $cy, $rx, $ry, $color, $stroke);

        return $this;
    }

    public function image(Framebuffer $source, float $x, float $y, ?float $width = null, ?float $height = null, float $opacity = 1.0, Filter $filter = Filter::NEAREST, ?array $radii = null, ?Color $tint = null, ?array $sourceRect = null): static
    {
        $this->recorder()->image($source, $x, $y, $width, $height, $opacity, $filter, $radii, $tint, $sourceRect);

        return $this;
    }

    public function roundRect(float $x, float $y, float $width, float $height, array $radii, Color $fill, ?Color $border = null, float $borderWidth = 0.0): static
    {
        $this->recorder()->roundRect($x, $y, $width, $height, $radii, $fill, $border, $borderWidth);

        return $this;
    }

    public function shadow(float $x, float $y, float $width, float $height, float $radius, float $blur, Color $color, float $dx = 0.0, float $dy = 0.0): static
    {
        $this->recorder()->shadow($x, $y, $width, $height, $radius, $blur, $color, $dx, $dy);

        return $this;
    }

    public function layer(float $x, float $y, float $width, float $height, callable $draw, float $opacity = 1.0, ?array $radii = null, ?FilterChain $filter = null, ?FilterChain $backdrop = null): static
    {
        // the children draw on this painter, so they are recorded where the layer is
        $this->recorder()->layer($x, $y, $width, $height, function () use ($draw): void {
            $draw($this);
        }, $opacity, $radii, $filter, $backdrop);

        return $this;
    }

    public function glass(float $x, float $y, float $width, float $height, ?array $radii, Glass $glass, float $opacity = 1.0): static
    {
        $this->recorder()->glass($x, $y, $width, $height, $radii, $glass, $opacity);

        return $this;
    }

    public function text(string $text, float $x, float $y, Color $color, Font $font): static
    {
        $this->recorder()->text($text, $x, $y, $color, $font);

        return $this;
    }

    public function textBounds(string $text, Font $font): array
    {
        return $this->recorder()->textBounds($text, $font);
    }

    /**
     * Whether the framebuffer still holds the last frame when the next is
     * drawn, so drawing only what changed leaves the rest correct. Every
     * engine repairs a ring before drawing, so its back holds the last frame
     * too: a ring keeps its frame whatever the engine.
     */
    protected function keepsFrame(Framebuffer $framebuffer): bool
    {
        return $framebuffer->preservesContentsOnPresent() || $framebuffer instanceof RingFramebuffer;
    }

    /** The region grown by $reach pixels (rounded up) on every side, kept on the surface. */
    protected static function grown(Region $region, float $reach, Region $surface): Region
    {
        $by = (int) ceil($reach);

        return (new Region($region->x - $by, $region->y - $by, $region->width + 2 * $by, $region->height + 2 * $by))->intersect($surface);
    }

    /** The pixels a command can write, null for none: what GlassGroups takes as drawn. */
    public function commandBox(array $command): ?Region
    {
        return $this->recorder()->box($command, $this->surface());
    }

    protected function surface(): Region
    {
        return Region::wholeSurface($this->width(), $this->height());
    }
}
