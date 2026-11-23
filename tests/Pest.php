<?php

declare(strict_types=1);

use Surface\Core\Providers\TopsideServiceProvider;
use Surface\Toolkits\ToolkitSession;
use Venusian\Topside\Tests\Fakes\FakeDriver;
use Venusian\Topside\Tests\Fakes\FakeProvider;
use Voyager\Config\Repository;
use Voyager\Core\RenderedInstance;

/*
| Every test gets a fresh framework core with topside registered and the fake toolkit installed
| and selected. Nothing here touches a native toolkit.
*/
pest()->beforeEach(function (): void {
    FakeDriver::$built = 0;
    $this->app = topside();
})->afterEach(function (): void {
    disconnectAllSessions();
})->in('Unit');

/**
 * Sessions outlive their test while anything holds them, and a connected one counts for the
 * one-toolkit-at-a-time rule on macOS: each test ends with none connected.
 */
function disconnectAllSessions(): void
{
    $live = (new ReflectionClass(ToolkitSession::class))->getStaticPropertyValue('live');
    $connected = [];
    foreach ($live ?? [] as $session => $_) {
        $connected[] = $session;
    }

    // Not inside that loop: disconnect() takes the session out of the WeakMap, and a WeakMap
    // that loses an entry while it is walked skips the next one.
    foreach ($connected as $session) {
        $session->disconnect();
    }
}

/**
 * A booted framework core with topside's providers, plus $providers, over $config.
 *
 * @param list<class-string> $providers
 */
function topside(array $config = [], array $providers = [FakeProvider::class], ?string $base_path = null): RenderedInstance
{
    $app = new RenderedInstance($base_path ?? sys_get_temp_dir());
    $app->registerInstance('config', new Repository(array_replace_recursive([
        'app' => ['name' => 'topside-tests'],
        'main' => ['title' => 'Topside Tests'],
        'toolkits' => ['default' => 'fake'],
    ], $config)));
    $app->register(TopsideServiceProvider::class);
    foreach ($providers as $provider) {
        $app->register($provider);
    }
    $app->boot();

    return $app;
}

/**
 * Short class names of $mail, in order.
 *
 * @param list<object> $mail
 * @return list<string>
 */
function mailNames(array $mail): array
{
    return array_map(static fn (object $m): string => (new ReflectionClass($m))->getShortName(), $mail);
}

require_once __DIR__.'/Helpers/views.php';
require_once __DIR__.'/Helpers/sharp.php';
require_once __DIR__.'/Helpers/menus.php';
