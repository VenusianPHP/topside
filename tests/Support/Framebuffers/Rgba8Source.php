<?php

declare(strict_types=1);

namespace Venusian\Topside\Tests\Support\Framebuffers;

use LogicException;
use Surface\Contracts\Rendering\Framebuffers\DamageGranularity;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Framebuffers\InstanceThatManagesAFramebuffer;
use Surface\Contracts\Rendering\Painting\PaintTarget;
use Surface\Contracts\Rendering\Framebuffers\Region;

/** RGBA8 bytes as a read-only framebuffer: what a fixture's rgba8 op blits from. Pixel words are 0xRRGGBBAA. */
final class Rgba8Source implements InstanceThatManagesAFramebuffer, PaintTarget
{
    public function __construct(private string $rgba8, private int $width, private int $height) {}

    public function viewportWidth(): int { return $this->width; }

    public function viewportHeight(): int { return $this->height; }

    public function hostFormat(): FormatSpec { return FormatSpec::rgba8(); }

    public function getPixel(int $x, int $y): int
    {
        return unpack('N', $this->rgba8, ($y * $this->width + $x) * 4)[1];
    }

    public function toRgba8(): string { return $this->rgba8; }

    public function dump(?int $layer = null): string { return $this->rgba8; }

    public function flush(FormatSpec $spec, bool $as_array = false): string|array { throw new LogicException('a source is only blitted from'); }

    public function flushRegion(Region $region, FormatSpec $spec, bool $as_array = false): string|array { throw new LogicException('a source is only blitted from'); }

    public function damageGranularity(): DamageGranularity { return DamageGranularity::pixel($this->width, $this->height); }

    public function preservesContentsOnPresent(): bool { return true; }

    public function pointer(): int { return 0; }

    public function blitTo(InstanceThatManagesAFramebuffer $target, int $offset_x = 0, int $offset_y = 0): InstanceThatManagesAFramebuffer { return $target->blitFrom($this, $offset_x, $offset_y); }

    public function setPixel(int $x, int $y, int $value): static { throw new LogicException('read-only source'); }

    public function setPixels(array $pixels): static { throw new LogicException('read-only source'); }

    public function setRegion(array $coordinates, int $value): static { throw new LogicException('read-only source'); }

    public function setSegment(int $x, int $y, int $width, int $height, int $color): static { throw new LogicException('read-only source'); }

    public function memory(): ?\Surface\Contracts\Rendering\Framebuffers\PixelMemory { return null; }

    public function drewInto(?\Surface\Contracts\Rendering\Framebuffers\Region ...$regions): static { throw new LogicException('read-only source'); }

    public function scratch(int $width, int $height): InstanceThatManagesAFramebuffer { throw new LogicException('read-only source'); }

    public function clear(): static { throw new LogicException('read-only source'); }

    public function fill(int $color): static { throw new LogicException('read-only source'); }

    public function blitFrom(InstanceThatManagesAFramebuffer $source, int $offset_x = 0, int $offset_y = 0): InstanceThatManagesAFramebuffer { throw new LogicException('read-only source'); }

    public function writeRgba8(string $rgba8, int $width, int $height, int $x = 0, int $y = 0): static { throw new LogicException('read-only source'); }

    public function scroll(Region $region, int $dx, int $dy): void { throw new LogicException('read-only source'); }
}
