<?php

namespace VelvetGE\Painting;

use ReflectionException;
use Surface\Contracts\Rendering\Painting\PaintTarget;
use Surface\Contracts\Rendering\Painting\Painter;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Rendering\Painters\PaintingDriver;
use VelvetGE\Contracts\Framebuffers\CompositingFramebuffer;

/**
 * Velvet on Topside's painting manager: the driver registered there as 'velvet', which hands
 * every painter to the module Velvet's configuration names (velvet.cpu.painters.default).
 */
class TopsidePaintbrushDriver extends PaintingDriver
{
    protected ?string $active_driver = null;

    public function __construct()
    {
        $this->active_driver = config('velvet.cpu.painters.default');
    }

    /**
     * The painter Velvet's active module makes.
     *
     * @throws ReflectionException
     */
    public function painter(CompositingFramebuffer&PaintTarget $target, ?Edges $edges = null): Painter
    {
        /** @var VelvetPaintbrushManager $mgr */
        $mgr = app('velvet.painters');

        return $mgr->driver($this->active_driver)->painter($target, $edges);
    }

    public function driver(): string
    {
        return 'velvet';
    }
}
