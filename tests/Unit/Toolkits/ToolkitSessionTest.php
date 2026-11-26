<?php

declare(strict_types=1);

use Surface\Toolkits\EventLoop\ToolkitPump;
use Surface\Toolkits\ToolkitSession;
use Surface\Toolkits\ToolkitException;
use Surface\Contracts\Toolkits\Styling\ColorScheme;
use Venusian\Topside\Tests\Fakes\FakeDriver;
use Venusian\Topside\Tests\Fakes\FakeLoop;
use Venusian\Topside\Tests\Fakes\FakeSession;
use Venusian\Topside\Tests\Fakes\OtherFakeSession;
use Venusian\Topside\Tests\Fakes\FakeWindow;

function session(): FakeSession
{
    return new FakeSession(test()->app);
}

it('starts the toolkit library on the first connect and never again', function (): void {
    $session = session();

    expect($session->connected())->toBeFalse();

    $session->connect();
    $toolkit = $session->application();
    $session->disconnect();
    $session->connect();

    expect($session->connected())->toBeTrue()
        ->and($session->bootstraps)->toBe(1)
        ->and($session->application())->toBe($toolkit)
        ->and($toolkit->connects)->toBe(2)
        ->and($toolkit->disconnects)->toBe(1);
});

it('applies the configured colour scheme once, as the toolkit starts', function (string $configured, ColorScheme $applied): void {
    test()->app['config']->set('toolkits.color_scheme', $configured);
    $session = session();

    $session->connect();
    $session->disconnect();
    $session->connect();

    expect($session->schemes)->toBe([$applied])
        ->and($session->colorScheme())->toBe($applied);
})->with([
    ['system', ColorScheme::SYSTEM],
    ['dark', ColorScheme::DARK],
    ['light', ColorScheme::LIGHT],
]);

it('applies the system scheme when none is configured, and refuses one it does not know', function (): void {
    test()->app['config']->set('toolkits.color_scheme', null);
    expect(session()->colorScheme())->toBe(ColorScheme::SYSTEM);

    test()->app['config']->set('toolkits.color_scheme', 'sepia');
    expect(fn () => session()->connect())
        ->toThrow(ToolkitException::class, "toolkits.color_scheme is 'system', 'dark' or 'light', got 'sepia'.");
});

it('connects and disconnects only once each', function (): void {
    $session = session();

    $session->connect()->connect();
    $session->disconnect();
    $session->disconnect();

    expect($session->application()->connects)->toBe(1)
        ->and($session->application()->disconnects)->toBe(1)
        ->and($session->connected())->toBeFalse();
});

it('holds mail until a loop is joined, then hands it over in order', function (): void {
    $session = session()->connect();
    $session->post($first = new stdClass());
    $session->post($second = new stdClass());

    $loop = new FakeLoop();
    $session->joinLoop($loop);

    expect($loop->posted)->toBe([$first, $second])
        ->and($session->takeOutbox())->toBe([]);

    $session->post($third = new stdClass());
    expect($loop->posted)->toBe([$first, $second, $third]);
});

it('joins a loop by folding its descriptor into the toolkit and crowning its pump', function (): void {
    $session = session()->connect();
    $loop = new FakeLoop(fd: 11);

    $session->joinLoop($loop);
    $session->joinLoop($loop);

    expect($session->application()->wake_descriptors)->toBe([11])
        ->and($loop->resources)->toHaveCount(1)
        ->and($loop->crowned)->toBe(array_key_first($loop->resources))
        ->and($loop->resources[$loop->crowned])->toBeInstanceOf(ToolkitPump::class);
});

it('flushes latest-only mail when it joins a loop', function (): void {
    $session = session()->connect();
    $session->postLatest('k', $mail = new stdClass());

    $session->joinLoop($loop = new FakeLoop());

    expect($loop->posted)->toBe([$mail]);
});

it('refuses to join a loop before it connects', function (): void {
    expect(fn () => session()->joinLoop(new FakeLoop()))
        ->toThrow(RuntimeException::class, 'Connect the session before joining a loop.');
});

it('refuses a loop whose waiter has no descriptor', function (): void {
    expect(fn () => session()->connect()->joinLoop(new FakeLoop(fd: null)))
        ->toThrow(RuntimeException::class, 'no descriptor');
});

it('joins the running application\'s event loop when it connects', function (): void {
    $loop = new FakeLoop();
    $this->app->markApplicationRunning();
    $this->app->registerInstance('event-loop', $loop);

    session()->connect();

    expect($loop->crowned)->not->toBeNull();
});

it('collapses latest-only mail to the last post under each key', function (): void {
    $session = session();
    $session->postLatest('a', new stdClass());
    $session->postLatest('a', $last_a = new stdClass());
    $session->postLatest('b', $b = new stdClass());
    $session->postLatest('c', new stdClass());
    $session->forgetLatest('c');

    $session->flushLatest();
    $session->flushLatest();

    expect($session->takeOutbox())->toBe([$last_a, $b]);
});

it('pumps the toolkit with the budget given and reports what it dispatched', function (): void {
    $session = session()->connect();
    $session->application()->dispatches = 5;

    expect($session->pump(16_000_000))->toBe(5)
        ->and($session->pump(0))->toBe(5)
        ->and($session->application()->pumps)->toBe([16_000_000, 0]);
});

it('settles each open window after the toolkit pumps', function (): void {
    $driver = new FakeDriver($this->app);
    $one = $driver->open('one', 10, 10);
    $two = $driver->open('two', 10, 10);
    $settled_during_pump = null;
    $driver->session()->application()->during_pump = function () use ($one, &$settled_during_pump): void {
        $settled_during_pump = $one->settles;
    };

    $driver->session()->pump(0);

    expect($settled_during_pump)->toBe(0)
        ->and($one->settles)->toBe(1)
        ->and($two->settles)->toBe(1);
});

it('stops settling a window it no longer watches and drops its pending resize', function (): void {
    $driver = new FakeDriver($this->app);
    /** @var FakeWindow $window */
    $window = $driver->open('main', 10, 10);
    $window->nativeResized(20, 20);

    $driver->session()->unwatchWindow($window);
    $driver->session()->pump(0);
    $driver->session()->flushLatest();

    expect($window->settles)->toBe(0)
        ->and($driver->session()->takeOutbox())->toBe([]);
});

it('keys window resizes by window name', function (): void {
    expect(ToolkitSession::resizedKey('main'))->toBe('window.resized.main')
        ->and(ToolkitSession::resizedKey('main'))->not->toBe(ToolkitSession::resizedKey('other'));
});

it('lets one toolkit at a time connect on macOS, and any number elsewhere', function (): void {
    $ours = session()->connect();
    $theirs = new OtherFakeSession($this->app);

    if (device_os_family() !== 'mac') {
        expect($theirs->connect()->connected())->toBeTrue();

        return;
    }

    expect(fn () => $theirs->connect())->toThrow(
        RuntimeException::class,
        "FakeSession is connected. On macOS one toolkit session pumps the application's events: disconnect it before connecting OtherFakeSession.",
    );
    expect($theirs->connected())->toBeFalse();

    $ours->disconnect();
    expect($theirs->connect()->connected())->toBeTrue();
});

it('connects any number of sessions of one toolkit', function (): void {
    $one = session()->connect();
    $two = session()->connect();

    expect($one->connected())->toBeTrue()
        ->and($two->connected())->toBeTrue();
});

it('leaves no session connected when a test ends, however many it connected', function (int $count): void {
    $sessions = [];
    for ($made = 0; $made < $count; $made++) {
        $sessions[] = session()->connect();
    }

    disconnectAllSessions();

    foreach ($sessions as $session) {
        expect($session->connected())->toBeFalse();
    }
})->with([1, 2, 3, 4, 5]);

it('gives each session its own pump on a shared loop', function (): void {
    $loop = new FakeLoop();

    session()->connect()->joinLoop($loop);
    session()->connect()->joinLoop($loop);

    expect($loop->resources)->toHaveCount(2);
    foreach (array_keys($loop->resources) as $name) {
        expect($name)->toStartWith('toolkit.pump.');
    }
});

it('does not join a loop while the application is not running', function (): void {
    $loop = new FakeLoop();
    $this->app->registerInstance('event-loop', $loop);

    session()->connect();

    expect($loop->crowned)->toBeNull();
});

it('does not resolve an event loop nothing has asked for yet', function (): void {
    $resolved = false;
    $this->app->markApplicationRunning();
    $this->app->registerSingleton('event-loop', function () use (&$resolved): FakeLoop {
        $resolved = true;

        return new FakeLoop();
    });

    session()->connect();

    expect($resolved)->toBeFalse();
});
