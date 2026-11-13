<?php

namespace Surface\VelvetGE\Framebuffers;

use Closure;
use Surface\Contracts\Graphics\Effects\FilterChain;
use Surface\Contracts\Rendering\Framebuffers\CompletePixelStore;
use Surface\Contracts\Rendering\Framebuffers\DamageGranularity;
use Surface\Contracts\Rendering\Framebuffers\EffectsPixelStore;
use Surface\Contracts\Rendering\Framebuffers\Filter;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Framebuffers\PaintingPixelStore;
use Surface\Contracts\Rendering\Framebuffers\PixelStore;
use Surface\Contracts\Rendering\Framebuffers\PixelStoreMinter;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\NutsAndBolts\GradientSpread;

/**
 * Any store as a complete one. What the store does itself goes straight to it. A tier it leaves
 * out runs on a VelvetGE store that is lent the store's pixels: its words are read out with
 * region(), the call runs there with VelvetGE's arithmetic, and the rect it touched is written
 * back with load(). So a library that only holds and moves pixels is a whole framebuffer, and
 * what it paints is byte for byte what VelvetGE paints.
 */
final class CompletingPixelStore implements CompletePixelStore
{
    /**
     * @param PixelStore $store Core, or core and one tier.
     * @param PixelStoreMinter $velvet Mints the store a missing tier runs on.
     */
    public function __construct(
        private readonly PixelStore $store,
        private readonly PixelStoreMinter $velvet,
    ) {}

    /** The store this one completes. */
    public function inner(): PixelStore
    {
        return $this->store;
    }

    public function width(): int
    {
        return $this->store->width();
    }

    public function height(): int
    {
        return $this->store->height();
    }

    public function format(): FormatSpec
    {
        return $this->store->format();
    }

    public function get(int $x, int $y): int
    {
        return $this->store->get($x, $y);
    }

    public function set(int $x, int $y, int $value): void
    {
        $this->store->set($x, $y, $value);
    }

    public function setPixels(array $pixels): ?Region
    {
        return $this->store->setPixels($pixels);
    }

    public function setCoordinates(array $coordinates, int $value): ?Region
    {
        return $this->store->setCoordinates($coordinates, $value);
    }

    public function rect(Region $region, int $value): void
    {
        $this->store->rect($region, $value);
    }

    public function fill(int $value): void
    {
        $this->store->fill($value);
    }

    public function bytes(?int $layer = null): string
    {
        return $this->store->bytes($layer);
    }

    public function region(Region $region, FormatSpec $spec): string
    {
        return $this->store->region($region, $spec);
    }

    public function load(Region $region, string $bytes): void
    {
        $this->store->load($region, $bytes);
    }

    public function toRgba8(): string
    {
        return $this->store->toRgba8();
    }

    public function blitRgba8(string $rgba8, int $width, int $height, int $offset_x, int $offset_y, ?Region $clip = null): ?Region
    {
        return $this->store->blitRgba8($rgba8, $width, $height, $offset_x, $offset_y, $clip);
    }

    public function copy(PixelStore $source, Region $region): void
    {
        $this->store->copy(self::bare($source), $region);
    }

    public function plane(int $value): string
    {
        return $this->store->plane($value);
    }

    public function pointer(): int
    {
        return $this->store->pointer();
    }

    public function spanSink(): ?object
    {
        return $this->store->spanSink();
    }

    public function granularity(): DamageGranularity
    {
        return $this->store->granularity();
    }

    public function paintSpans(string $spans, int $rgba8): ?Region
    {
        return $this->store instanceof PaintingPixelStore
            ? $this->store->paintSpans($spans, $rgba8)
            : $this->lent(fn (CompletePixelStore $velvet): ?Region => $velvet->paintSpans($spans, $rgba8));
    }

    public function paintMasks(array $masks, int $rgba8, Region $clip): ?Region
    {
        return $this->store instanceof PaintingPixelStore
            ? $this->store->paintMasks($masks, $rgba8, $clip)
            : $this->lent(fn (CompletePixelStore $velvet): ?Region => $velvet->paintMasks($masks, $rgba8, $clip));
    }

    public function paintGradient(string $spans, string $ramp, string $kind, GradientSpread $spread, array $inverse, int $row = 0): ?Region
    {
        return $this->store instanceof PaintingPixelStore
            ? $this->store->paintGradient($spans, $ramp, $kind, $spread, $inverse, $row)
            : $this->lent(fn (CompletePixelStore $velvet): ?Region => $velvet->paintGradient($spans, $ramp, $kind, $spread, $inverse, $row));
    }

    public function paintRgba8(string $rgba8, int $width, int $height, array $inverse, Region $target, int $opacity, Filter $filter, int $row = 0, int $tint = 0xFFFFFFFF, ?array $window = null, ?string $coverage = null): void
    {
        if ($this->store instanceof PaintingPixelStore) {
            $this->store->paintRgba8($rgba8, $width, $height, $inverse, $target, $opacity, $filter, $row, $tint, $window, $coverage);

            return;
        }
        $this->lent(function (CompletePixelStore $velvet) use ($rgba8, $width, $height, $inverse, $target, $opacity, $filter, $row, $tint, $window, $coverage): Region {
            $velvet->paintRgba8($rgba8, $width, $height, $inverse, $target, $opacity, $filter, $row, $tint, $window, $coverage);

            return $target;
        });
    }

    public function filter(FilterChain $chain, Region $region, int $row = 0, bool $premultiplied = false): void
    {
        if ($this->store instanceof EffectsPixelStore) {
            $this->store->filter($chain, $region, $row, $premultiplied);

            return;
        }
        $this->lent(function (CompletePixelStore $velvet) use ($chain, $region, $row, $premultiplied): Region {
            $velvet->filter($chain, $region, $row, $premultiplied);

            return $region;
        });
    }

    public function kawaseDown(PixelStore $source, Region $region): void
    {
        if ($this->store instanceof EffectsPixelStore) {
            $this->store->kawaseDown(self::bare($source), $region);

            return;
        }
        $from = $this->readable($source);
        $this->lent(function (CompletePixelStore $velvet) use ($from, $region): Region {
            $velvet->kawaseDown($from, $region);

            return Region::wholeSurface($velvet->width(), $velvet->height());
        }, false);
    }

    public function kawaseUp(PixelStore $source): void
    {
        if ($this->store instanceof EffectsPixelStore) {
            $this->store->kawaseUp(self::bare($source));

            return;
        }
        $from = $this->readable($source);
        $this->lent(function (CompletePixelStore $velvet) use ($from): Region {
            $velvet->kawaseUp($from);

            return Region::wholeSurface($velvet->width(), $velvet->height());
        }, false);
    }

    public function paintGlass(array $geometry, PixelStore $blur, int $scale, ?PixelStore $sharp, int $originX, int $originY, array $look, Region $clip): ?Region
    {
        if ($this->store instanceof EffectsPixelStore) {
            return $this->store->paintGlass($geometry, self::bare($blur), $scale, is_null($sharp) ? null : self::bare($sharp), $originX, $originY, $look, $clip);
        }
        $from = $this->readable($blur);
        $over = is_null($sharp) ? null : $this->readable($sharp);

        return $this->lent(fn (CompletePixelStore $velvet): ?Region => $velvet->paintGlass($geometry, $from, $scale, $over, $originX, $originY, $look, $clip));
    }

    /**
     * Run $call on a VelvetGE store holding this store's pixels, then write back the rect it
     * answers (null: it touched nothing). A call that throws writes nothing back.
     *
     * @param Closure(CompletePixelStore): (Region|null) $call
     * @param bool $filled Whether the VelvetGE store starts with this store's pixels; false when the call overwrites all of it.
     */
    // chisle: the whole surface is lent for each call; lend only the touched rect if a missing tier shows up in a profile
    private function lent(Closure $call, bool $filled = true): ?Region
    {
        $velvet = $this->velvet->mint($this->store->format(), $this->store->width(), $this->store->height());
        if ($filled) {
            self::pour($this->store, $velvet);
        }
        $touched = $call($velvet);
        if (! is_null($touched)) {
            $this->store->load($touched, $velvet->region($touched, $velvet->format()));
        }

        return $touched;
    }

    /**
     * $source as a store the lent VelvetGE store can read: itself when it is already the kind
     * VelvetGE mints for its format, else a VelvetGE store holding its pixels.
     */
    private function readable(PixelStore $source): PixelStore
    {
        $source = self::bare($source);
        $velvet = $this->velvet->mint($source->format(), $source->width(), $source->height());

        return $source::class === $velvet::class ? $source : self::pour($source, $velvet);
    }

    /** All of $from's pixels into $velvet, word for word; answers $velvet. */
    private static function pour(PixelStore $from, PixelStore $velvet): PixelStore
    {
        $whole = Region::wholeSurface($from->width(), $from->height());
        $velvet->load($whole, $from->region($whole, $from->format()));

        return $velvet;
    }

    /** The store under a completing one, so two stores of one library meet as themselves. */
    private static function bare(PixelStore $store): PixelStore
    {
        return $store instanceof self ? $store->store : $store;
    }
}
