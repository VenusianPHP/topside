<?php

namespace Surface\Contracts\Rendering\Framebuffers;

/**
 * What every framebuffer module offers: a block of pixels with a size, a format and an address.
 * What only some modules offer is in the contracts that extend this one: CompositingFramebuffer,
 * ScrollingFramebuffer, EffectsFramebuffer, and the kinds (damage tracking, paged, ring, ePaper).
 */
interface InstanceThatManagesAFramebuffer
{
    public function viewportWidth(): int;

    public function viewportHeight(): int;

    public function hostFormat(): FormatSpec;

    /**
     * Where the pixels are in native memory, for drawing into them in place, or null when there is no such
     * place: PHP holds the bytes, or the framebuffer holds one page of a larger surface. Whoever writes there
     * reports the rects with drewInto().
     */
    public function memory(): ?PixelMemory;

    /** Native address of the bytes; 0 when PHP owns them. */
    public function pointer(): int;

    /**
     * Records rects written straight into memory(), as every painting call records what it paints. A null is a
     * draw that touched nothing. Whoever draws into memory() calls this; nothing else knows the bytes changed.
     *
     * @throws FramebufferException When the framebuffer has no memory() to have been written into.
     */
    public function drewInto(?Region ...$regions): static;

    public function getPixel(int $x, int $y): int;

    public function setPixel(int $x, int $y, int $value): static;

    /**
     * @param  array<int, array{0: int, 1: int, 2: int}>  $pixels  Each entry is [x, y, color]
     */
    public function setPixels(array $pixels): static;

    /**
     * @param  array<int, array{0: int, 1: int}>  $coordinates
     */
    public function setRegion(array $coordinates, int $value): static;

    public function setSegment(int $x, int $y, int $width, int $height, int $color): static;

    public function clear(): static;

    public function fill(int $color): static;

    /**
     * Put $width × $height RGBA8 pixels (top-left first) on the surface with
     * their top-left at ($x, $y): each replaces the pixel there, mapped into
     * the host format; what falls off the surface is dropped. A paged buffer
     * takes surface coordinates and keeps the rows on its current page. Bytes
     * other than $width × $height × 4 throw and nothing is written.
     */
    public function writeRgba8(string $rgba8, int $width, int $height, int $x = 0, int $y = 0): static;

    public function blitTo(InstanceThatManagesAFramebuffer $target, int $offset_x = 0, int $offset_y = 0): InstanceThatManagesAFramebuffer;

    public function blitFrom(InstanceThatManagesAFramebuffer $source, int $offset_x = 0, int $offset_y = 0): InstanceThatManagesAFramebuffer;

    /** RGBA8 bytes, top-left first, viewport size. */
    public function toRgba8(): string;

    /**
     * Raw host bytes (optional layer).
     */
    public function dump(?int $layer = null): string;

    /**
     * Emit pixels in the requested FormatSpec.
     *
     * @return string|array<int, mixed>
     */
    public function flush(FormatSpec $spec, bool $as_array = false): string|array;

    /**
     * Bytes of one sub-rect in the requested FormatSpec. $region must already be
     * snapped to damageGranularity(); a region that is not is a caller bug.
     *
     * @return string|list<int>
     */
    public function flushRegion(Region $region, FormatSpec $spec, bool $as_array = false): string|array;

    /**
     * A new framebuffer of $width x $height pixels, blank, in RGBA8, of this framebuffer's driver: somewhere to
     * draw a layer before it lands.
     */
    public function scratch(int $width, int $height): InstanceThatManagesAFramebuffer;

    public function damageGranularity(): DamageGranularity;

    /**
     * True when the logical canvas still holds the previous frame after a present.
     */
    public function preservesContentsOnPresent(): bool;
}
