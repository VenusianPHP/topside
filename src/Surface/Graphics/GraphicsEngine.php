<?php

namespace Surface\Graphics;

use Surface\Contracts\Graphics\DrawingException;
use Surface\Contracts\Graphics\GraphicsEngine as GraphicsEngineContract;
use Surface\Contracts\Graphics\Part;
use Surface\Contracts\Rendering\Framebuffers\Framebuffer;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Contracts\Rendering\Rasterization\ShapeRasterizer;
use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Contracts\Rendering\Raycasting\Raycaster;

/**
 * What every engine starts from: it offers the parts it lists, and each part it leaves out throws
 * DrawingException naming the engine and the part. An engine overrides the parts it has.
 */
abstract class GraphicsEngine implements GraphicsEngineContract
{
    /** @return list<Part> The parts this engine has. */
    abstract protected function parts(): array;

    public function offers(Part $part): bool
    {
        return in_array($part, $this->parts(), true);
    }

    public function framebuffer(int $width, int $height, array $args = []): Framebuffer
    {
        throw DrawingException::notOffered($this->name(), Part::FRAMEBUFFER);
    }

    public function rasterizer(Region $clip, ?Edges $edges = null): ShapeRasterizer
    {
        throw DrawingException::notOffered($this->name(), Part::RASTERIZER);
    }

    public function raycaster(Grid $grid): Raycaster
    {
        throw DrawingException::notOffered($this->name(), Part::RAYCASTER);
    }

    /**
     * 'edges' as an engine takes it: an Edges, 'hard' or 'antialiased'. Null when it is not given:
     * the engine picks.
     *
     * @param  array<string, mixed>  $args
     *
     * @throws DrawingException When 'edges' is anything else.
     */
    public static function edgesFrom(array $args): ?Edges
    {
        $edges = $args['edges'] ?? null;
        if (is_string($edges)) {
            $edges = Edges::tryFrom($edges) ?? throw new DrawingException("'edges' is 'hard' or 'antialiased', got '{$edges}'.");
        }
        if (! is_null($edges) && ! $edges instanceof Edges) {
            throw new DrawingException("'edges' is 'hard' or 'antialiased', or an Edges.");
        }

        return $edges;
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  list<string>  $takes
     *
     * @throws DrawingException When $args has a key the engine does not take.
     */
    protected function accept(array $args, array $takes): void
    {
        foreach (array_keys($args) as $key) {
            if (! in_array($key, $takes, true)) {
                throw new DrawingException("{$this->name()} does not take '{$key}'. It takes: ".implode(', ', $takes).'.');
            }
        }
    }

    /** @throws DrawingException When a size is not a whole number of at least 1. */
    protected function size(mixed $width, mixed $height): void
    {
        if (! is_int($width) || ! is_int($height)) {
            throw new DrawingException("'width' and 'height' are integers.");
        }
        if ($width < 1 || $height < 1) {
            throw new DrawingException("'width' and 'height' are at least 1.");
        }
    }
}
