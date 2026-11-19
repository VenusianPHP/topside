<?php

namespace VelvetGE\Rasterization;

use ReflectionException;
use Surface\Contracts\Rendering\deprecated\Framebuffers\Region;
use Surface\Contracts\Rendering\deprecated\Rasterization\Edges;
use Surface\Contracts\Rendering\deprecated\Rasterization\Rasterizer;
use Surface\Rendering\deprecated\Rasterization\RasterizationDriver;

/**
 * Velvet on Topside's rasterization manager: the driver registered there as 'velvet', which hands
 * every rasterizer to the module Velvet's configuration names (velvet.cpu.rasterizers.default).
 */
class TopsideRasterizationDriver extends RasterizationDriver
{
    protected ?string $active_driver = null;

    public function __construct()
    {
        $this->active_driver = config('velvet.cpu.rasterizers.default');
    }

    /**
     * The rasterizer Velvet's active module makes.
     *
     * @throws ReflectionException
     */
    public function rasterizer(Region $clip, Edges $edges): Rasterizer
    {
        /** @var VelvetRasterizationManager $mgr */
        $mgr = app('velvet.rasterizers');

        return $mgr->driver($this->active_driver)->rasterizer($clip, $edges);
    }

    public function driver(): string
    {
        return 'velvet';
    }
}
