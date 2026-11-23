<?php

declare(strict_types=1);

use Surface\Toolkits\Presentation;
use Surface\Toolkits\ToolkitAdapters;
use Surface\Toolkits\ToolkitShell;
use Venusian\Topside\Tests\Fakes\FakeLoop;
use Venusian\Topside\Tests\Fakes\FakeWindow;

/*
| The two calls a toolkit application makes at start:
|   $app->make(ApplicationShell::class)->open();
|   Presentation::driver()->present();
| The presentation tree is registered once the running application's event loop resolves.
*/
beforeEach(function (): void {
    $this->loop = new FakeLoop();
    $this->app->markApplicationRunning();
    $this->app->registerSingleton('event-loop', fn () => $this->loop);
    $this->app->make('event-loop');
});

it('opens the first window at the configured size, titled, on the default toolkit', function (): void {
    topsideRunning(['main' => ['presentation' => ['window_size' => [800, 600]]]]);

    (new ToolkitShell())->open();

    /** @var FakeWindow $window */
    $window = ToolkitAdapters::driver()->windows()['topside-tests'];
    expect($window->size())->toBe([800, 600])
        ->and($window->title)->toBe('Topside Tests')
        ->and($window->presents)->toBe(0);
});

it('presents the window it opened', function (): void {
    (new ToolkitShell())->open();
    Presentation::driver()->present();

    /** @var FakeWindow $window */
    $window = ToolkitAdapters::driver()->windows()['topside-tests'];
    expect($window->presents)->toBe(1);
});

it('joins the toolkit\'s session to the event loop', function (): void {
    (new ToolkitShell())->open();

    expect($this->loop->crowned)->not->toBeNull()
        ->and(ToolkitAdapters::driver()->session()->application()->wake_descriptors)->toBe([7]);
});

/** A fresh core over $config, running, with its event loop resolved. */
function topsideRunning(array $config): void
{
    $app = topside($config);
    $loop = test()->loop;
    $app->markApplicationRunning();
    $app->registerSingleton('event-loop', fn () => $loop);
    $app->make('event-loop');
    test()->app = $app;
}

it('opens the first window at 640x480 when no size is configured', function (): void {
    config(['main.presentation' => ['mode' => 'toolkit']]);

    (new ToolkitShell())->open();

    expect(ToolkitAdapters::driver()->windows()['topside-tests']->size())->toBe([640, 480]);
});

it('joins a session connected before the event loop resolved, once it does', function (): void {
    $app = topside();
    $loop = new FakeLoop();
    $app->markApplicationRunning();
    $app->registerSingleton('event-loop', fn () => $loop);

    ToolkitAdapters::driver()->connect();
    expect($loop->crowned)->toBeNull();

    $app->make('event-loop');
    expect($loop->crowned)->not->toBeNull();
});

it('loads the application\'s navigation routes once the event loop resolves', function (): void {
    $base = sys_get_temp_dir().'/topside-routes-'.bin2hex(random_bytes(4));
    mkdir("{$base}/routes", recursive: true);
    file_put_contents("{$base}/routes/navigation.php", '<?php $GLOBALS["topside_app_routes_loaded"] = true;');
    unset($GLOBALS['topside_app_routes_loaded']);

    $app = topside(base_path: $base);
    $app->markApplicationRunning();
    $app->registerSingleton('event-loop', fn () => new FakeLoop());
    $app->make('event-loop');

    expect($GLOBALS['topside_app_routes_loaded'] ?? false)->toBeTrue();
});

it('starts with a toolkit driver that has not connected yet when the event loop resolves', function (): void {
    $app = topside();
    $loop = new FakeLoop();
    $app->markApplicationRunning();
    $app->registerSingleton('event-loop', fn () => $loop);
    $driver = ToolkitAdapters::driver();

    $app->make('event-loop');

    expect($driver->session())->toBeNull()
        ->and($loop->crowned)->toBeNull();
});
