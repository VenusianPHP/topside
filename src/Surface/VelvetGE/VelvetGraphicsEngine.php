<?php

namespace Surface\VelvetGE;

use Surface\Contracts\Graphics\DrawingException;
use Surface\Contracts\Graphics\OutputTarget;
use Surface\Contracts\Graphics\Part;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Framebuffers\Framebuffer;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Painting\Painter;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Contracts\Rendering\Rasterization\Rasterizer;
use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Contracts\Rendering\Raycasting\Raycaster;
use Surface\Graphics\GraphicsEngine;
use Surface\Rendering\Framebuffers\FramebufferLibraryManager;
use Surface\Rendering\Painters\PaintingLibraryManager;
use Surface\Rendering\Rasterization\RasterizationLibraryManager;
use Surface\Rendering\Raycasting\RaycastingLibraryManager;

/**
 * The CPU graphics engine. Its parts are Rendering's drivers, so each is whichever driver the
 * application has ('auto' unless named): VelvetGE's own in PHP or C, or a library's. Any of its
 * parts works with any other.
 *
 *     $velvet->framebuffer(320, 240);                                   // RGBA8, kept whole
 *     $velvet->framebuffer(320, 240, ['mode' => 'ring', 'frames' => 3]);
 *     $velvet->painter(['width' => 320, 'height' => 240]);
 *     $velvet->painter(['framebuffer' => $frame, 'edges' => 'hard']);
 *     $velvet->painter(['output' => $canvas]);
 */
class VelvetGraphicsEngine extends GraphicsEngine
{
    /** What framebuffer() takes besides a size. */
    protected const array FRAMEBUFFER_ARGUMENTS = ['mode', 'format', 'page_rows', 'frames', 'framebuffers'];

    /** What painter() takes. */
    protected const array PAINTER_ARGUMENTS = ['output', 'framebuffer', 'width', 'height', 'mode', 'format', 'page_rows', 'frames', 'framebuffers', 'edges', 'painters'];

    /**
     * @param  RaycastingLibraryManager|null  $raycasters  Null when the application has not turned raycasting on.
     */
    public function __construct(
        protected FramebufferLibraryManager $framebuffers,
        protected RasterizationLibraryManager $rasterize,
        protected PaintingLibraryManager $painters,
        protected ?RaycastingLibraryManager $raycasters = null,
    ) {}

    public function name(): string
    {
        return 'velvet';
    }

    protected function parts(): array
    {
        return is_null($this->raycasters)
            ? [Part::FRAMEBUFFER, Part::RASTERIZER, Part::PAINTER]
            : [Part::FRAMEBUFFER, Part::RASTERIZER, Part::RAYCASTER, Part::PAINTER];
    }

    /**
     * 'mode' is full (the default), dirty, epaper, paged (with 'page_rows') or ring (with 'frames',
     * 2 unless given). 'format' is a FormatSpec, RGBA8 unless given. 'framebuffers' names the
     * framebuffer driver; config's unless given.
     */
    public function framebuffer(int $width, int $height, array $args = []): Framebuffer
    {
        $this->accept($args, self::FRAMEBUFFER_ARGUMENTS);
        $this->size($width, $height);

        $format = $args['format'] ?? FormatSpec::rgba8();
        if (! $format instanceof FormatSpec) {
            throw new DrawingException("'format' is a FormatSpec.");
        }
        foreach (['page_rows', 'frames'] as $key) {
            if (isset($args[$key]) && ! is_int($args[$key])) {
                throw new DrawingException("'{$key}' is an integer.");
            }
        }
        $driver = $this->framebuffers->driver($args['framebuffers'] ?? null);
        $mode = $args['mode'] ?? 'full';

        return match ($mode) {
            'full' => $driver->full($format, $width, $height),
            'dirty' => $driver->dirty($format, $width, $height),
            'epaper' => $driver->epaper($format, $width, $height),
            'paged' => $driver->paged($format, $width, $height, $args['page_rows'] ?? throw new DrawingException("'paged' needs 'page_rows'.")),
            'ring' => $driver->ring($format, $width, $height, $args['frames'] ?? 2),
            default => throw new DrawingException("'mode' is one of full, dirty, epaper, paged, ring, got '".(is_string($mode) ? $mode : get_debug_type($mode))."'."),
        };
    }

    /** Answers coverage spans as well as painting: a CPU rasterizer does both. Anti-aliased unless $edges is given. */
    public function rasterizer(Region $clip, ?Edges $edges = null): Rasterizer
    {
        return $this->rasterize->driver()->rasterizer($clip, $edges ?? Edges::ANTIALIASED);
    }

    public function raycaster(Grid $grid): Raycaster
    {
        if (is_null($this->raycasters)) {
            throw DrawingException::notOffered($this->name(), Part::RAYCASTER, "Turn raycasting on: config/rendering.php's raycaster.enabled (RENDERING_RAYCASTER=true).");
        }

        return $this->raycasters->driver()->caster($grid);
    }

    /**
     * A painter over an output target's own framebuffer ('output'), over one handed in
     * ('framebuffer'), or over one made here from 'width', 'height' and what framebuffer() takes.
     * 'edges' is an Edges, 'hard' or 'antialiased'; the painter picks by the format unless given.
     * 'painters' names the painting driver; config's unless given.
     */
    public function painter(array $args = []): Painter
    {
        $this->accept($args, self::PAINTER_ARGUMENTS);

        return $this->painters->driver($args['painters'] ?? null)->painter($this->target($args), self::edgesFrom($args));
    }

    /**
     * @param  array<string, mixed>  $args
     *
     * @throws DrawingException When the arguments do not describe one framebuffer.
     */
    protected function target(array $args): Framebuffer
    {
        $made = ['width', 'height', ...self::FRAMEBUFFER_ARGUMENTS];

        if (array_key_exists('output', $args)) {
            if (! $args['output'] instanceof OutputTarget) {
                throw new DrawingException("'output' is an OutputTarget: a canvas or a display.");
            }
            foreach (['framebuffer', ...$made] as $key) {
                if (array_key_exists($key, $args)) {
                    throw new DrawingException("'output' comes alone: '{$key}' describes a framebuffer to be made.");
                }
            }

            return $args['output']->framebuffer();
        }

        if (array_key_exists('framebuffer', $args)) {
            if (! $args['framebuffer'] instanceof Framebuffer) {
                throw new DrawingException("'framebuffer' is a Framebuffer.");
            }
            foreach ($made as $key) {
                if (array_key_exists($key, $args)) {
                    throw new DrawingException("'framebuffer' comes alone: '{$key}' describes one to be made.");
                }
            }

            return $args['framebuffer'];
        }

        if (! isset($args['width'], $args['height'])) {
            throw new DrawingException("velvet needs a 'framebuffer', or a 'width' and a 'height' to make one.");
        }
        $this->size($args['width'], $args['height']);

        return $this->framebuffer($args['width'], $args['height'], array_intersect_key($args, array_flip(self::FRAMEBUFFER_ARGUMENTS)));
    }
}
