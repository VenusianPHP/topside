<?php

namespace Surface\Graphics;

use Surface\Contracts\Graphics\ActorStage;
use Surface\Contracts\Graphics\Backend;
use Surface\Contracts\Graphics\GraphicsException;
use Surface\Contracts\Graphics\Resource;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Contracts\Rendering\Raycasting\Grid;

/**
 * One graphics library, on one backend. Everything drawn with it is made from it, and what it
 * makes works only with what it made: a framebuffer of one engine is not a target for another's
 * painter.
 *
 * An engine hands out its own version of each GE type. What is behind them is the engine's own
 * business: nothing here says how its pixels are held or its shapes drawn.
 */
abstract class GraphicsEngine
{
    public function __construct(
        protected readonly Backend $backend,
    ) {}

    /** The name it is registered by: 'velvet', 'metal'. */
    abstract public function name(): string;

    public function backend(): Backend
    {
        return $this->backend;
    }

    /**
     * What the engine is built from, for showing to a person: a name for each part and what
     * drives it. An engine that is one library through and through answers nothing.
     *
     * @return array<string, string>
     */
    public function modules(): array
    {
        return [];
    }

    /** Whether this engine, as it is now, hands out that resource. */
    abstract public function supports(Resource $resource): bool;

    /**
     * A framebuffer with no head: nothing shows it.
     *
     * @param FormatSpec|null $format null: the engine's own
     */
    abstract public function framebuffer(int $width, int $height, ?FormatSpec $format = null): GEFramebuffer;

    /**
     * A framebuffer of $stage's size in pixels, which present() shows on it. The size is taken
     * now: when the stage is resized, attach to it again.
     *
     * @param FormatSpec|null $format null: the engine's own
     *
     * @throws GraphicsException When the stage has no size yet.
     */
    public function attach(ActorStage $stage, ?FormatSpec $format = null): GEFramebuffer
    {
        [$width, $height] = $stage->pixelSize();
        if ($width < 1 || $height < 1) {
            throw GraphicsException::stageHasNoSize();
        }

        return $this->framebuffer($width, $height, $format)->onto($stage);
    }

    /** @throws GraphicsException When the engine has none. */
    public function rasterizer(Edges $edges = Edges::ANTIALIASED): GERasterizer
    {
        throw GraphicsException::unsupported($this->name(), Resource::RASTERIZER);
    }

    /** @throws GraphicsException When the engine has none. */
    public function raycaster(Grid $grid): GERaycaster
    {
        throw GraphicsException::unsupported($this->name(), Resource::RAYCASTER);
    }

    /**
     * @param GEFramebuffer $onto one of this engine's
     *
     * @throws GraphicsException When the engine has none, or $onto is another engine's.
     */
    public function painter(GEFramebuffer $onto): GEPainter
    {
        throw GraphicsException::unsupported($this->name(), Resource::PAINTER);
    }

    /** Lets go of what the engine holds in its library. Its resources are of no use after. */
    public function release(): void {}
}
