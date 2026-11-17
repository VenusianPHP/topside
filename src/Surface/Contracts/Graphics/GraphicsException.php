<?php

namespace Surface\Contracts\Graphics;

use Surface\Contracts\Core\TopsideException;
use Surface\Contracts\Rendering\Framebuffers\Region;

class GraphicsException extends TopsideException
{
    /** @param list<string> $known */
    public static function unknownEngine(string $name, array $known): self
    {
        return new self("No graphics engine is registered as [{$name}]. Registered: ".($known === [] ? 'none' : implode(', ', $known)).'.');
    }

    public static function unknownBackend(string $backend): self
    {
        return new self("[{$backend}] is not a backend: use 'cpu', 'gpu' or 'auto'.");
    }

    /** @param list<Backend> $offered */
    public static function backendNotOffered(string $engine, Backend $backend, array $offered): self
    {
        $has = implode(', ', array_map(fn (Backend $b): string => $b->value, $offered));

        return new self("The [{$engine}] engine has no {$backend->value} backend. It offers: {$has}.");
    }

    public static function backendNotReady(string $engine, Backend $backend): self
    {
        return new self("The [{$engine}] engine's {$backend->value} backend is not built yet.");
    }

    public static function noBackends(string $engine): self
    {
        return new self("The [{$engine}] engine was registered with no backend: name at least one.");
    }

    public static function foreignResource(string $own, string $other): self
    {
        return new self("A surface of the [{$own}] engine was given one of the [{$other}] engine: an engine's surfaces work only with each other.");
    }

    public static function outsideSurface(Region $region, int $width, int $height): self
    {
        return new self("A region of {$region->width}x{$region->height} at ({$region->x}, {$region->y}) is not inside a {$width}x{$height} surface.");
    }

    public static function wrongSize(int $bytes, Region $region): self
    {
        $want = $region->width * $region->height * 4;

        return new self("{$bytes} bytes were given for a {$region->width}x{$region->height} region: RGBA8 takes {$want}.");
    }

    public static function noStage(): self
    {
        return new self('This surface has no stage to present on: make it with the engine\'s surface().');
    }

    public static function stageHasNoSize(): self
    {
        return new self('The stage has no size yet: make its surface once it is laid out and shown.');
    }

    public static function badSize(int $width, int $height): self
    {
        return new self("A surface of {$width}x{$height} cannot be made: both sides are 1 or more.");
    }
}
