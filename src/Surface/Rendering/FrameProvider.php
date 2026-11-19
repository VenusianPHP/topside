<?php

namespace Surface\Rendering;

use Surface\Contracts\Rendering\FrameProvider as FrameProviderContract;
use Surface\Rendering\Concerns\ReadsBackendConfig;

/**
 * A library's frame provider, holding its settings: the config given, shaped like the one stored at $location.
 */
abstract class FrameProvider implements FrameProviderContract
{
    use ReadsBackendConfig;

    protected array $config;

    /**
     * @param string $location where its stored config lives, as config() reads it
     */
    public function __construct(array $config, string $location)
    {
        $this->config = $this->backendConfig($config, $location);
    }
}
