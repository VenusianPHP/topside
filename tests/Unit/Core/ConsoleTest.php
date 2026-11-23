<?php

declare(strict_types=1);

use Voyager\Config\Repository;
use Voyager\Core\RenderedInstance;
use Surface\Toolkits\Views\Sharp;
use Surface\Toolkits\ToolkitsServiceProvider;
use Surface\Core\Console\SharpCacheCommand;

describe("topside's computer commands", function (): void {
    it('has sharp:cache when topside is installed', function (): void {
        expect($this->app->has(SharpCacheCommand::class))->toBeTrue()
            ->and($this->app->make(SharpCacheCommand::class)->getName())->toBe('sharp:cache');
    });

    it('leaves it out of the toolkits component alone, which still has Sharp', function (): void {
        $app = new RenderedInstance(sys_get_temp_dir());
        $app->registerInstance('config', new Repository([]));
        $app->register(ToolkitsServiceProvider::class);

        $commands = array_filter(
            array_keys((new ReflectionProperty($app, 'bindings'))->getValue($app)),
            fn (string $bound): bool => str_ends_with($bound, 'Command'),
        );

        expect($commands)->toBe([])->and($app->has(Sharp::class))->toBeTrue();
    });
});
