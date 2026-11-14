<?php

namespace Surface\VelvetGE;

use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Framebuffers\Framebuffer;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Graphics\GEFramebuffer;
use Surface\Graphics\GraphicsEngine;
use Surface\NutsAndBolts\Color;
use Surface\VelvetGE\Framebuffers\PixelMapper;

/** Velvet's framebuffer: one of Rendering's, from whichever framebuffer driver the application has. */
class VelvetGEFramebuffer extends GEFramebuffer
{
    public function __construct(
        GraphicsEngine $engine,
        protected readonly Framebuffer $inner,
    ) {
        parent::__construct($engine);
    }

    /**
     * The Rendering framebuffer behind this one, for Velvet's own resources.
     *
     * @internal
     */
    public function inner(): Framebuffer
    {
        return $this->inner;
    }

    public function width(): int
    {
        return $this->inner->viewportWidth();
    }

    public function height(): int
    {
        return $this->inner->viewportHeight();
    }

    public function format(): FormatSpec
    {
        return $this->inner->hostFormat();
    }

    public function clear(Color $color): static
    {
        // fill() takes a pixel as the framebuffer holds it, so the colour is packed in its format first
        $rgba = self::rgba($color);
        $this->inner->fill(PixelMapper::for($this->inner->hostFormat())->fromRgba8($rgba >> 24, ($rgba >> 16) & 0xFF, ($rgba >> 8) & 0xFF, $rgba & 0xFF));

        return $this;
    }

    protected function readRgba8(Region $region): string
    {
        return $this->inner->flushRegion($region, FormatSpec::rgba8());
    }

    protected function writeRgba8(string $rgba8, Region $region): void
    {
        $this->inner->writeRgba8($rgba8, $region->width, $region->height, $region->x, $region->y);
    }

    /** A colour as Rendering takes one: 0xRRGGBBAA. */
    public static function rgba(Color $color): int
    {
        return ((int) round($color->red * 255) << 24) | ((int) round($color->green * 255) << 16) | ((int) round($color->blue * 255) << 8) | (int) round($color->alpha * 255);
    }
}
