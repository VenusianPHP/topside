<?php

namespace Surface\VelvetGE\Framebuffers;

use Surface\Contracts\Rendering\Framebuffers\BitDepth;
use Surface\Contracts\Rendering\Framebuffers\EInkColor;
use Surface\Contracts\Rendering\Framebuffers\ePaperFramebuffer as ePaperFramebufferContract;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Framebuffers\FramebufferException;
use Surface\Contracts\Rendering\Framebuffers\PixelFormat;
use Surface\Contracts\Rendering\Framebuffers\PixelStoreMinter;

/**
 * Planar planes, single-ink mono, or packed palette codes: the three ways an
 * ePaper controller takes its RAM. Refuses colour depths. Starts as paper.
 */
class ePaperFramebuffer extends StoreFramebuffer implements ePaperFramebufferContract
{
    protected int $paper;

    public function __construct(PixelStoreMinter $minter, FormatSpec $format, int $width, int $height)
    {
        $supported = match (true) {
            $format->pixel_format === PixelFormat::PLANAR && $format->bit_depth === BitDepth::B1 && ! is_null($format->palette) => true,
            $format->pixel_format === PixelFormat::MONO_HORIZONTAL && $format->bit_depth === BitDepth::B1 => true,
            $format->pixel_format === PixelFormat::ROW_MAJOR && in_array($format->bit_depth, [BitDepth::B2, BitDepth::B4, BitDepth::B8], true) && ! is_null($format->palette) => true,
            default => false,
        };
        if (! $supported) {
            throw FramebufferException::unsupportedFormat($format, 'ePaper takes PLANAR + palette, MONO_HORIZONTAL B1, or ROW_MAJOR B2/B4/B8 + palette.');
        }

        parent::__construct($minter, $format, $width, $height);

        $this->paper = PixelMapper::for($format)->fromRgba8(255, 255, 255);
        $this->store->fill($this->paper);
    }

    public function paper(): int
    {
        return $this->paper;
    }

    public function clear(): static
    {
        return $this->fill($this->paper);
    }

    public function channelDump(EInkColor $ink): string
    {
        if ($this->format->pixel_format === PixelFormat::MONO_HORIZONTAL) {
            return $ink === EInkColor::BLACK
                ? $this->store->bytes()
                : throw new FramebufferException("{$ink->name} is not an ink of a single-ink mono panel.");
        }

        $position = $this->format->palette->indexOf($ink->value);
        if (is_null($position)) {
            throw new FramebufferException("{$ink->name} is not in this panel's palette.");
        }

        return $this->format->pixel_format === PixelFormat::PLANAR
            ? $this->store->bytes($position)
            : $this->store->plane($this->format->palette->codes()[$position]);
    }
}
