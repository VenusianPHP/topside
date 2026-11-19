<?php

namespace VelvetGE;

use Surface\Contracts\Graphics\ActorStage;
use Surface\Contracts\Graphics\GraphicsEngine;
use Surface\Rendering\Managers\DrawCommandManager;
use Surface\Rendering\Managers\DrawerManager;
use Surface\Rendering\Managers\FrameManager;

/**
 * Velvet: Topside's Rendering underneath. Each surface records its frames with a recorder of its own, gets its pixel
 * memory from Rendering's default frame provider, and has Rendering's default drawer draw only what changed.
 */
final class VelvetDevice implements GraphicsEngine
{
    public function __construct(
        private readonly FrameManager $frames,
        private readonly DrawerManager $drawers,
        private readonly DrawCommandManager $recorders,
        bool $gpu = false,
    ) {
        $gpu && throw VelvetGEException::noGpu($this->name());
    }

    public function name(): string
    {
        return 'velvet';
    }

    public function gpu(): bool
    {
        return false;
    }

    public function surface(ActorStage $stage): VelvetSurface
    {
        return new VelvetSurface($this->frames->driver(), $this->drawers->driver(), $this->recorders->fresh(), $stage);
    }

    public function offscreen(int $width, int $height): VelvetSurface
    {
        return new VelvetSurface($this->frames->driver(), $this->drawers->driver(), $this->recorders->fresh(), null, $width, $height);
    }

    /** Velvet keeps nothing between surfaces: each surface's frame goes with it. */
    public function release(): void {}
}
