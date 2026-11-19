<?php

namespace Surface\Contracts\Graphics;

use RuntimeException;

class GraphicsException extends RuntimeException
{
    public static function noneConfigured(): static
    {
        return new static('No graphics engine is configured: set graphics.engine.default (GRAPHICS_ENGINE) to a registered name.');
    }

    /**
     * @param list<string> $registered
     */
    public static function notRegistered(string $engine, array $registered): static
    {
        $names = $registered === [] ? 'No graphics engine is registered' : 'Registered: '.implode(', ', $registered);

        return new static("Graphics engine [{$engine}] is not registered. {$names}.");
    }

    public static function noGpu(string $engine): static
    {
        return new static("Graphics engine [{$engine}] draws on the CPU only: ask for it with gpu: false.");
    }

    public static function nothingPushed(string $engine): static
    {
        return new static("[{$engine}] pop() without a push().");
    }

    public static function noStage(string $engine): static
    {
        return new static("A [{$engine}] offscreen surface has no stage to present to.");
    }
}
