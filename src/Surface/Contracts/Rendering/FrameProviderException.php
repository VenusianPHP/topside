<?php

namespace Surface\Contracts\Rendering;

use InvalidArgumentException;

class FrameProviderException extends InvalidArgumentException
{
    public static function cannotMakeFrame(string $provider, int $width, int $height, string $limits): static
    {
        return new static("Frame provider [{$provider}] cannot make a {$width}x{$height} frame: {$limits}.");
    }

    public static function extensionMissing(string $provider, string $extension): static
    {
        return new static("Frame provider [{$provider}] needs ext-{$extension}, which is not loaded.");
    }

    public static function noneConfigured(): static
    {
        return new static('No frame provider is configured: set rendering.frames.default (RENDERING_FRAMES) to a registered name.');
    }

    /**
     * @param list<string> $registered
     */
    public static function notRegistered(string $provider, array $registered): static
    {
        $names = $registered === [] ? 'No frame provider is registered' : 'Registered: '.implode(', ', $registered);

        return new static("Frame provider [{$provider}] is not registered. {$names}.");
    }
}
