<?php

namespace Surface\Typography;

use ReflectionException;
use Surface\Typography\Catalogs\FontCatalogLibraryManager;
use Surface\Typography\Faces\FontFaceLibraryManager;
use Surface\Typography\Shapers\FontShaperLibraryManager;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\NutsAndBolts\ServiceProvider;

/**
 * Typography is Topside's, for every engine. It brings no driver of its own: packages register their
 * catalogs, faces and shapers on its three managers.
 */
class TypographyServiceProvider extends ServiceProvider
{
    /** @throws ReflectionException */
    public function register(): void
    {
        $this->app->registerSingleton('typography.catalogs', fn (FrameworkCore $app) => new FontCatalogLibraryManager($app));
        $this->app->alias('typography.catalogs', FontCatalogLibraryManager::class);
        $this->app->registerSingleton('typography.faces', fn (FrameworkCore $app) => new FontFaceLibraryManager($app));
        $this->app->alias('typography.faces', FontFaceLibraryManager::class);
        $this->app->registerSingleton('typography.shapers', fn (FrameworkCore $app) => new FontShaperLibraryManager($app));
        $this->app->alias('typography.shapers', FontShaperLibraryManager::class);

        $this->app->registerSingleton('typography', fn (FrameworkCore $app) => new FontRegistry(
            config('typography.fonts', []),
            config('typography.default'),
            fn (string $path) => $app->basePath($path),
        ));
        $this->app->alias('typography', FontRegistry::class);

        $this->app->registerSingleton('typesetter', fn (FrameworkCore $app) => new Typesetter(
            $app->make('typography.catalogs')->driver(),
            $app->make('typography.faces')->driver(),
            $app->make('typography.shapers')->driver(),
        ));
        $this->app->alias('typesetter', Typesetter::class);
    }
}
