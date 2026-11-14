<?php

namespace Surface\Toolkits\Primitives;

use Surface\Contracts\Toolkits\Primitives\TKCanvas as PrimitiveContract;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;

/**
 * A region of a toolkit window that shows a graphics engine's frames. It has no size of its own:
 * the layout sizes it, so give it fill() or a minSize().
 */
abstract class TKCanvas extends TKPrimitive implements PrimitiveContract
{
    public function pixelSize(): array
    {
        $this->live();
        [$width, $height] = $this->nativeSize();
        $scale = $this->nativeScale();

        return [(int) round($width * $scale), (int) round($height * $scale)];
    }

    public function pixelScale(): float
    {
        $this->live();

        return $this->nativeScale();
    }

    /** @throws ToolkitRuntimeException When the bytes are not $width x $height pixels. */
    public function display(string $rgba8, int $width, int $height): void
    {
        $this->live();
        if ($width < 1 || $height < 1 || strlen($rgba8) !== $width * $height * 4) {
            throw new ToolkitRuntimeException("Canvas '{$this->path()}' was given ".strlen($rgba8)." bytes for {$width}x{$height} pixels: RGBA8 takes ".($width * $height * 4).'.');
        }
        $this->applyPixels($rgba8, $width, $height);
    }

    /** A canvas lends nothing unless its toolkit says what it is to the platform. */
    public function nativeSurface(): ?object
    {
        return null;
    }

    /** Device pixels to one unit of the toolkit's layout, where this canvas is shown. */
    abstract protected function nativeScale(): float;

    /** The pixels as what the native shows, stretched over its bounds. */
    abstract protected function applyPixels(string $rgba8, int $width, int $height): void;
}
