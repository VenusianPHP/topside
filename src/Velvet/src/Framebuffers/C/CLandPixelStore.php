<?php

namespace VelvetGE\Framebuffers\C;

use FbBuffer;
use Surface\Contracts\Graphics\deprecated2\Effects\FilterChain;
use Surface\Contracts\Rendering\deprecated\Framebuffers\DamageGranularity;
use Surface\Contracts\Rendering\deprecated\Framebuffers\Filter;
use Surface\Contracts\Rendering\deprecated\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\deprecated\Framebuffers\FramebufferException;
use Surface\Contracts\Rendering\deprecated\Framebuffers\PixelMemory;
use Surface\Contracts\Rendering\deprecated\Framebuffers\Region;
use Surface\NutsAndBolts\GradientSpread;
use ValueError;
use VelvetGE\Contracts\Framebuffers\CompletePixelStore;
use VelvetGE\Contracts\Framebuffers\PixelStore;
use VelvetGE\Contracts\Framebuffers\TicketPixelStore;
use VelvetGE\Framebuffers\Layout;
use VelvetGE\Framebuffers\PHP\PHPLandPixelStore;

/**
 * The pixel glob in C: one FbBuffer. Every operation is one call into ext-fb;
 * what ext-fb refuses with ValueError is rethrown as FramebufferException.
 */
final class CLandPixelStore implements CompletePixelStore, TicketPixelStore
{
    private FbBuffer $buffer;

    public function __construct(
        private readonly FormatSpec $format,
        private readonly int $width,
        private readonly int $height,
    ) {
        Layout::size($width, $height);
        $this->buffer = new FbBuffer(FbFormats::from($format), $width, $height);
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    public function format(): FormatSpec
    {
        return $this->format;
    }

    public function get(int $x, int $y): int
    {
        try {
            return $this->buffer->get($x, $y);
        } catch (ValueError $e) {
            throw new FramebufferException($e->getMessage(), previous: $e);
        }
    }

    public function set(int $x, int $y, int $value): void
    {
        try {
            $this->buffer->set($x, $y, $value);
        } catch (ValueError $e) {
            throw new FramebufferException($e->getMessage(), previous: $e);
        }
    }

    public function setPixels(array $pixels): ?Region
    {
        try {
            return self::region_of($this->buffer->setPixels($pixels));
        } catch (ValueError $e) {
            throw new FramebufferException($e->getMessage(), previous: $e);
        }
    }

    public function setCoordinates(array $coordinates, int $value): ?Region
    {
        try {
            return self::region_of($this->buffer->setCoordinates($coordinates, $value));
        } catch (ValueError $e) {
            throw new FramebufferException($e->getMessage(), previous: $e);
        }
    }

    public function rect(Region $region, int $value): void
    {
        try {
            $this->buffer->rect($region->x, $region->y, $region->width, $region->height, $value);
        } catch (ValueError $e) {
            throw new FramebufferException($e->getMessage(), previous: $e);
        }
    }

    public function fill(int $value): void
    {
        $this->buffer->fill($value);
    }

    public function bytes(?int $layer = null): string
    {
        try {
            return is_null($layer) ? $this->buffer->bytes() : $this->buffer->layer($layer);
        } catch (ValueError $e) {
            throw new FramebufferException($e->getMessage(), previous: $e);
        }
    }

    public function region(Region $region, FormatSpec $spec): string
    {
        $to = $spec->equals($this->format) ? null : FbFormats::from($spec);

        try {
            return $this->buffer->region($region->x, $region->y, $region->width, $region->height, $to);
        } catch (ValueError $e) {
            throw new FramebufferException($e->getMessage(), previous: $e);
        }
    }

    /**
     * ext-fb has no call that takes a rect's bytes in the store's own layout. RGBA8 rows are what
     * blitRgba8() takes, so an RGBA8 store is written in one call; any other layout goes word by
     * word through setPixels().
     */
    public function load(Region $region, string $bytes): void
    {
        if ($region->isEmpty() || $region->x < 0 || $region->y < 0 || $region->right() > $this->width || $region->bottom() > $this->height) {
            throw FramebufferException::outsideSurface($region, $this->width, $this->height);
        }
        if ($this->format->equals(FormatSpec::rgba8())) {
            if (strlen($bytes) !== $region->width * $region->height * 4) {
                throw new FramebufferException('load() takes the '.($region->width * $region->height * 4)." bytes of a {$region->width}x{$region->height} rect in this format, got ".strlen($bytes).'.');
            }
            $this->blitRgba8($bytes, $region->width, $region->height, $region->x, $region->y);

            return;
        }

        $words = new PHPLandPixelStore($this->format, $region->width, $region->height);
        $words->load(Region::wholeSurface($region->width, $region->height), $bytes);
        $pixels = [];
        for ($y = 0; $y < $region->height; $y++) {
            for ($x = 0; $x < $region->width; $x++) {
                $pixels[] = [$region->x + $x, $region->y + $y, $words->get($x, $y)];
            }
        }
        $this->setPixels($pixels);
    }

    public function toRgba8(): string
    {
        return $this->buffer->rgba8();
    }

    public function blitRgba8(string $rgba8, int $width, int $height, int $offset_x, int $offset_y, ?Region $clip = null): ?Region
    {
        try {
            return self::region_of($this->buffer->blitRgba8($rgba8, $width, $height, $offset_x, $offset_y, $clip?->x ?? 0, $clip?->y ?? 0, $clip?->width, $clip?->height));
        } catch (ValueError $e) {
            throw new FramebufferException($e->getMessage(), previous: $e);
        }
    }

    public function kawaseDown(PixelStore $source, Region $region): void
    {
        try {
            $this->buffer->kawaseDown(self::fb($source), $region->x, $region->y, $region->width, $region->height);
        } catch (ValueError $e) {
            throw new FramebufferException($e->getMessage(), previous: $e);
        }
    }

    public function kawaseUp(PixelStore $source): void
    {
        try {
            $this->buffer->kawaseUp(self::fb($source));
        } catch (ValueError $e) {
            throw new FramebufferException($e->getMessage(), previous: $e);
        }
    }

    public function paintGlass(array $geometry, PixelStore $blur, int $scale, ?PixelStore $sharp, int $originX, int $originY, array $look, Region $clip): ?Region
    {
        try {
            return self::region_of($this->buffer->paintGlass(array_slice($geometry, 0, 4), $geometry[4], self::fb($blur), $scale, $sharp === null ? null : self::fb($sharp),
                $originX, $originY, $look, $clip->x, $clip->y, $clip->width, $clip->height));
        } catch (ValueError $e) {
            throw new FramebufferException($e->getMessage(), previous: $e);
        }
    }

    /** The FbBuffer of a level, which glass takes from stores of this driver only. */
    private static function fb(PixelStore $store): FbBuffer
    {
        return $store instanceof self ? $store->buffer : throw new FramebufferException('Glass levels and the target are stores of one driver.');
    }

    /** Its FbBuffer's bytes, with the buffer as their owner: ext-fb packs rows with no padding. */
    public function memory(): ?PixelMemory
    {
        return new PixelMemory($this->buffer->pointer(), $this->width, $this->height, intdiv($this->buffer->size(), $this->height), $this->format, $this->buffer);
    }

    public function paintSpans(string $spans, int $rgba8): ?Region
    {
        try {
            return self::region_of($this->buffer->paintSpans($spans, $rgba8));
        } catch (ValueError $e) {
            throw new FramebufferException($e->getMessage(), previous: $e);
        }
    }

    public function paintMasks(array $masks, int $rgba8, Region $clip): ?Region
    {
        try {
            return self::region_of($this->buffer->paintMasks($masks, $rgba8, $clip->x, $clip->y, $clip->width, $clip->height));
        } catch (ValueError $e) {
            throw new FramebufferException($e->getMessage(), previous: $e);
        }
    }

    public function filter(FilterChain $chain, Region $region, int $row = 0, bool $premultiplied = false): void
    {
        [$steps, $maps] = FilterSteps::encode($chain);
        try {
            $this->buffer->filter($steps, $maps, $region->x, $region->y, $region->width, $region->height, $row, $premultiplied);
        } catch (ValueError $e) {
            throw new FramebufferException($e->getMessage(), previous: $e);
        }
    }

    public function paintGradient(string $spans, string $ramp, string $kind, GradientSpread $spread, array $inverse, int $row = 0): ?Region
    {
        try {
            return self::region_of($this->buffer->paintGradient($spans, $ramp, $kind === 'radial', ['pad' => 0, 'repeat' => 1, 'reflect' => 2][$spread->value], $inverse, $row));
        } catch (ValueError $e) {
            throw new FramebufferException($e->getMessage(), previous: $e);
        }
    }

    public function paintRgba8(string $rgba8, int $width, int $height, array $inverse, Region $target, int $opacity, Filter $filter, int $row = 0, int $tint = 0xFFFFFFFF, ?array $window = null, ?string $coverage = null): void
    {
        try {
            $this->buffer->paintRgba8($rgba8, $width, $height, $inverse, $target->x, $target->y, $target->width, $target->height, $opacity, $filter === Filter::LINEAR, $row, $tint, $window, $coverage);
        } catch (ValueError $e) {
            throw new FramebufferException($e->getMessage(), previous: $e);
        }
    }

    public function copy(PixelStore $source, Region $region): void
    {
        if ($source->width() !== $this->width || $source->height() !== $this->height || ! $source->format()->equals($this->format)) {
            throw new FramebufferException('copy() takes a store of the same size and format.');
        }

        try {
            if ($source instanceof self) {
                $this->buffer->copy($source->buffer, $region->x, $region->y, $region->width, $region->height);

                return;
            }

            // A store of another flavor: check the rect, then move the words one by one.
            $this->buffer->region($region->x, $region->y, $region->width, $region->height);
            for ($y = $region->y; $y < $region->bottom(); $y++) {
                for ($x = $region->x; $x < $region->right(); $x++) {
                    $this->buffer->set($x, $y, $source->get($x, $y));
                }
            }
        } catch (ValueError $e) {
            throw new FramebufferException($e->getMessage(), previous: $e);
        }
    }

    /** An address in a layout ext-fb has, rows packed. */
    public function takes(PixelMemory $source): bool
    {
        return ! is_null(self::over($source));
    }

    public function blitMemory(PixelMemory $source, Region $from, int $x, int $y, ?Region $clip = null): ?Region
    {
        try {
            return self::region_of($this->buffer->blit(self::over($source) ?? throw new FramebufferException('This store cannot read that memory.'), $from->x, $from->y, $from->width, $from->height, $x, $y, $clip?->x ?? 0, $clip?->y ?? 0, $clip?->width, $clip?->height));
        } catch (ValueError $e) {
            throw new FramebufferException($e->getMessage(), previous: $e);
        }
    }

    public function paintMemory(PixelMemory $source, array $inverse, Region $target, int $opacity, Filter $filter, int $row = 0, int $tint = 0xFFFFFFFF, ?array $window = null, ?string $coverage = null): void
    {
        try {
            $this->buffer->paintBuffer(self::over($source) ?? throw new FramebufferException('This store cannot read that memory.'), $inverse, $target->x, $target->y, $target->width, $target->height, $opacity, $filter === Filter::LINEAR, $row, $tint, $window, $coverage);
        } catch (ValueError $e) {
            throw new FramebufferException($e->getMessage(), previous: $e);
        }
    }

    public function shift(Region $source, int $dx, int $dy): void
    {
        try {
            $this->buffer->blit($this->buffer, $source->x, $source->y, $source->width, $source->height, $source->x + $dx, $source->y + $dy);
        } catch (ValueError $e) {
            throw new FramebufferException($e->getMessage(), previous: $e);
        }
    }

    /**
     * An FbBuffer standing over the ticket's address, or null when ext-fb cannot read that layout.
     * A stride wider than a packed row is the padding between rows; ext-fb reads the pixels and leaves the padding.
     */
    private static function over(PixelMemory $source): ?FbBuffer
    {
        try {
            $format = FbFormats::from($source->format);
        } catch (FramebufferException) {
            return null;
        }
        $packed = $format->size($source->width, 1);
        if ($packed < 1 || $source->stride < $packed) {
            return null;
        }

        return FbBuffer::over($format, $source->width, $source->height, $source->address, $source->owner, $source->stride === $packed ? 0 : $source->stride);
    }

    public function plane(int $value): string
    {
        return $this->buffer->plane($value);
    }

    public function pointer(): int
    {
        return $this->buffer->pointer();
    }

    public function granularity(): DamageGranularity
    {
        [$unit_width, $unit_height] = $this->buffer->granularity();

        return new DamageGranularity($unit_width, $unit_height, $this->width, $this->height);
    }

    /** @param array{int, int, int, int}|null $rect */
    private static function region_of(?array $rect): ?Region
    {
        return is_null($rect) ? null : new Region(...$rect);
    }
}
