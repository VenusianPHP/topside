<?php

declare(strict_types=1);

use Venusian\Topside\Tests\Fakes\FakeDriver;
use Venusian\Topside\Tests\Fakes\FakeSession;
use Venusian\Topside\Tests\Fakes\FakeWindow;

it('has no session until it connects', function (): void {
    expect((new FakeDriver($this->app))->session())->toBeNull();
});

it('connects one session and keeps it', function (): void {
    $driver = new FakeDriver($this->app);

    $session = $driver->connect();

    expect($session)->toBeInstanceOf(FakeSession::class)
        ->and($session->connected())->toBeTrue()
        ->and($driver->connect())->toBe($session)
        ->and($driver->session())->toBe($session)
        ->and($session->bootstraps)->toBe(1);
});

it('opens its declared window class through its connected session', function (): void {
    $driver = new FakeDriver($this->app);

    $window = $driver->open('main', 320, 200);

    expect($window)->toBeInstanceOf(FakeWindow::class)
        ->and($window->name())->toBe('main')
        ->and($window->native())->toEqual((object) ['width' => 320, 'height' => 200])
        ->and($driver->session()->connected())->toBeTrue()
        ->and($driver->windows())->toBe(['main' => $window]);
});

it('refuses a second window under an open name', function (): void {
    $driver = new FakeDriver($this->app);
    $driver->open('main', 10, 10);

    expect(fn () => $driver->open('main', 10, 10))->toThrow(RuntimeException::class, "A window named 'main' is already open.");
});

it('frees a name it forgets', function (): void {
    $driver = new FakeDriver($this->app);
    $driver->open('main', 10, 10);

    $driver->forget('main');

    expect($driver->windows())->toBe([])
        ->and($driver->open('main', 10, 10))->toBeInstanceOf(FakeWindow::class);
});

it('runs its connected hook once the session is connected', function (): void {
    $driver = new FakeDriver($this->app);

    $driver->connect();

    expect($driver->connected_hook)->toBe([true]);
});
