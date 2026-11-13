<?php

namespace Surface\VelvetGE\Recording;

use InvalidArgumentException;
use PixelQueue;
use PixelQueueException;
use Surface\Contracts\Graphics\DrawingException;
use Surface\Contracts\Rendering\Framebuffers\DamageGranularity;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Rasterization\FillRule;
use Surface\Contracts\Rendering\Recording\ShapeQueue;
use Surface\NutsAndBolts\Affine;

/**
 * VelvetGE's queue in C: ext-pixel-queue holds the frame, the transform, the clip and the pushes,
 * records the plain shapes itself, and compares two frames. The commands, the numbers in them, the
 * regions and the refusals are the PHP queue's.
 */
final class CLandQueue implements ShapeQueue
{
    private PixelQueue $queue;

    /** The transform as an Affine, kept until it changes. */
    private ?Affine $matrix = null;

    public function __construct()
    {
        $this->queue = new PixelQueue(FillRule::NON_ZERO, FillRule::EVEN_ODD);
    }

    public function begin(Region $surface): void
    {
        $this->matrix = null;
        // the extension opens a frame on at least one pixel; a surface with none gets its empty clip here
        $this->queue->begin(max(1, $surface->width), max(1, $surface->height), $surface);
        if ($surface->isEmpty()) {
            $this->queue->clip($surface, 0, 0, $surface->width, $surface->height);
        }
    }

    public function recording(): bool
    {
        return $this->queue->recording();
    }

    public function abandon(): void
    {
        $this->matrix = null;
        $this->queue->abandon();
    }

    public function finish(): array
    {
        $this->matrix = null;

        return $this->queue->finish();
    }

    public function push(): void
    {
        $this->queue->push();
    }

    public function pop(): void
    {
        $this->matrix = null;
        $this->queue->pop();
    }

    public function depth(): int
    {
        return $this->queue->depth();
    }

    public function compose(Affine $matrix): void
    {
        try {
            $this->queue->concat($matrix->a, $matrix->b, $matrix->c, $matrix->d, $matrix->e, $matrix->f);
        } catch (PixelQueueException $e) {
            // what Affine's own constructor says of the product
            throw new InvalidArgumentException("Affine components are finite, got {$e->value}.");
        }
        $this->matrix = null;
    }

    public function matrix(): Affine
    {
        return $this->matrix ??= new Affine(...$this->queue->matrix());
    }

    public function clip(Region $clip): void
    {
        $this->queue->clip($clip, $clip->x, $clip->y, $clip->width, $clip->height);
    }

    public function clipRegion(): Region
    {
        return $this->queue->clipValue();
    }

    public function append(array $command): void
    {
        switch ($command[0]) {
            case 'path':
            case 'gradpath':
                $this->queue->append($command, PIXEL_QUEUE_CONTOURS, 1.0);

                return;
            case 'polyline':
                $this->queue->append($command, PIXEL_QUEUE_POINTS, 2.0 * $command[2] + 1.0);

                return;
            case 'spans':
                $this->queue->append($command, PIXEL_QUEUE_SPANS);

                return;
            case 'clear':
                if (! isset($command[2])) {
                    $this->queue->append($command, PIXEL_QUEUE_WHOLE);

                    return;
                }
                $area = $command[2];
                $this->queue->append($command, PIXEL_QUEUE_PIECES, 0.0, [$area->x, $area->y, $area->right(), $area->bottom(), $area->x, $area->y, $area->width, $area->height]);

                return;
        }

        // every other kind says where it writes itself: a pane of glass with a shadow writes in two places
        $pieces = $command[0] === 'glass' && $command[5] !== null
            ? [...self::piece([...array_slice($command, 0, 5), null, $command[6]]), ...self::piece($command[5])]
            : self::piece($command);
        $reads = Boxes::reads($command);
        $this->queue->append(
            $command, PIXEL_QUEUE_PIECES, 0.0, $pieces, Boxes::restless($command),
            is_null($reads) ? null : [$reads[0]->x, $reads[0]->y, $reads[0]->width, $reads[0]->height, $reads[1]],
        );
    }

    public function enter(Region $clip): void
    {
        $this->queue->enter($clip, $clip->x, $clip->y, $clip->width, $clip->height);
    }

    public function leave(): array
    {
        return $this->queue->leave();
    }

    public function drawn(Region $surface, DamageGranularity $granularity, array $also = []): array
    {
        return self::regions($this->queue->drawn(
            $surface->width, $surface->height, $granularity->unit_width, $granularity->unit_height,
            $granularity->surface_width, $granularity->surface_height, self::flat($also),
        ));
    }

    public function changed(Region $surface, DamageGranularity $granularity, array $also = []): array
    {
        return self::regions($this->queue->changed(
            $surface->width, $surface->height, $granularity->unit_width, $granularity->unit_height,
            $granularity->surface_width, $granularity->surface_height, self::flat($also),
        ));
    }

    public function clear(int $rgba): void
    {
        try {
            $this->queue->clear($rgba);
        } catch (PixelQueueException $e) {
            throw self::refused($e, 'clear');
        }
    }

    public function fillRect(float $x, float $y, float $width, float $height, int $rgba): void
    {
        try {
            $this->queue->fillRect($x, $y, $width, $height, $rgba);
        } catch (PixelQueueException $e) {
            throw self::refused($e, 'fillRect');
        }
    }

    public function strokeRect(float $x, float $y, float $width, float $height, int $rgba, float $stroke): void
    {
        try {
            $this->queue->strokeRect($x, $y, $width, $height, $rgba, $stroke);
        } catch (PixelQueueException $e) {
            throw self::refused($e, 'strokeRect');
        }
    }

    public function line(float $x0, float $y0, float $x1, float $y1, int $rgba, float $stroke): void
    {
        try {
            $this->queue->line($x0, $y0, $x1, $y1, $rgba, $stroke);
        } catch (PixelQueueException $e) {
            throw self::refused($e, 'line');
        }
    }

    public function fillTriangle(float $x0, float $y0, float $x1, float $y1, float $x2, float $y2, int $rgba): void
    {
        try {
            $this->queue->fillTriangle($x0, $y0, $x1, $y1, $x2, $y2, $rgba);
        } catch (PixelQueueException $e) {
            throw self::refused($e, 'fillTriangle');
        }
    }

    public function fillEllipse(float $cx, float $cy, float $rx, float $ry, int $rgba): void
    {
        try {
            $this->queue->fillEllipse($cx, $cy, $rx, $ry, $rgba);
        } catch (PixelQueueException $e) {
            throw self::refused($e, 'fillEllipse');
        }
    }

    public function strokeEllipse(float $cx, float $cy, float $rx, float $ry, int $rgba, float $stroke): void
    {
        try {
            $this->queue->strokeEllipse($cx, $cy, $rx, $ry, $rgba, $stroke);
        } catch (PixelQueueException $e) {
            throw self::refused($e, 'strokeEllipse');
        }
    }

    public function roundRect(float $x, float $y, float $width, float $height, array $radii, int $fill, ?int $border, float $borderWidth): void
    {
        try {
            $this->queue->roundRect($x, $y, $width, $height, $radii, $fill, $border, $borderWidth);
        } catch (PixelQueueException $e) {
            throw self::refused($e, 'roundRect');
        }
    }

    public function shadow(float $x, float $y, float $width, float $height, float $radius, float $blur, int $rgba, float $dx, float $dy): void
    {
        try {
            $this->queue->shadow($x, $y, $width, $height, $radius, $blur, $rgba, $dx, $dy);
        } catch (PixelQueueException $e) {
            throw self::refused($e, 'shadow');
        }
    }

    /** The extension's refusal as the DrawingException VelvetGE's recorder throws for the same input. */
    private static function refused(PixelQueueException $e, string $call): DrawingException
    {
        return match ($e->getCode()) {
            PIXEL_QUEUE_NOT_RECORDING => DrawingException::outsideFrame($call),
            PIXEL_QUEUE_NOT_FINITE => DrawingException::notFinite($e->what),
            PIXEL_QUEUE_PAST_LIMIT => DrawingException::pastLimit($e->what, $e->value),
            PIXEL_QUEUE_BAD_RADII => DrawingException::badRadii(),
            PIXEL_QUEUE_UNEVEN => DrawingException::unevenShadow(),
            default => new DrawingException($e->getMessage(), 0, $e),
        };
    }

    /**
     * Where a command writes, for the extension: its reach, then its clip.
     *
     * @param  array<int, mixed>  $command
     * @return array{float, float, float, float, int, int, int, int}
     */
    private static function piece(array $command): array
    {
        $clip = $command[array_key_last($command)];

        return [...Boxes::extent($command), $clip->x, $clip->y, $clip->width, $clip->height];
    }

    /**
     * @param  list<Region>  $regions
     * @return list<int> x, y, width, height of each
     */
    private static function flat(array $regions): array
    {
        $flat = [];
        foreach ($regions as $region) {
            array_push($flat, $region->x, $region->y, $region->width, $region->height);
        }

        return $flat;
    }

    /**
     * @param  list<int>  $flat  x, y, width, height of each
     * @return list<Region>
     */
    private static function regions(array $flat): array
    {
        $regions = [];
        for ($i = 0, $n = count($flat); $i < $n; $i += 4) {
            $regions[] = new Region($flat[$i], $flat[$i + 1], $flat[$i + 2], $flat[$i + 3]);
        }

        return $regions;
    }
}
