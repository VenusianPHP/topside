<?php

declare(strict_types=1);

use PHPUnit\Framework\MockObject\Stub;
use Surface\Rendering\RenderingServiceProvider;
use Voyager\Contracts\Core\FrameworkCore;

/**
 * Rendering's provider registered on an application that reads its config from $items. The application is a
 * stand-in that keeps what the provider registers: this package does not install the framework's own.
 */
function renderingApp(FrameworkCore&Stub $app, array $items = []): FrameworkCore
{
    $config = new class($items) {
        public function __construct(private readonly array $items) {}

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->items[$key] ?? $default;
        }
    };
    $singletons = $aliases = $made = [];

    $app->method('registerSingleton')->willReturnCallback(function (string $abstract, callable $concrete) use (&$singletons): void {
        $singletons[$abstract] = $concrete;
    });
    $app->method('alias')->willReturnCallback(function (string $abstract, string $alias) use (&$aliases): void {
        $aliases[$alias] = $abstract;
    });
    $app->method('make')->willReturnCallback(function (string $abstract) use ($app, $config, &$singletons, &$aliases, &$made): mixed {
        $abstract = $aliases[$abstract] ?? $abstract;

        return $abstract === 'config' ? $config : $made[$abstract] ??= $singletons[$abstract]($app);
    });

    (new RenderingServiceProvider($app))->register();

    return $app;
}
