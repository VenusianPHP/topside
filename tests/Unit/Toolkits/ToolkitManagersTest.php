<?php

declare(strict_types=1);

use Surface\Toolkits\Managers\ToolkitAdapterManager;
use Surface\Toolkits\ToolkitAdapters;
use Surface\Toolkits\ToolkitException;
use Surface\Toolkits\ToolkitWindows;
use Venusian\Topside\Tests\Fakes\FakeDriver;
use Venusian\Topside\Tests\Fakes\FakeWindow;

it('selects the default toolkit from toolkits.default', function (): void {
    expect(ToolkitAdapters::driver())->toBeInstanceOf(FakeDriver::class);
});

it('says so when no toolkit is selected', function (): void {
    topside(['toolkits' => ['default' => 'null']]);

    expect(fn () => ToolkitAdapters::driver())->toThrow(ToolkitException::class, 'No default toolkit set.');
});

it('names a toolkit nothing registered', function (): void {
    topside(['toolkits' => ['default' => 'missing']]);

    expect(fn () => ToolkitAdapters::driver())->toThrow(InvalidArgumentException::class, 'Driver [missing] not supported.');
});

it('opens windows on the default toolkit', function (): void {
    $window = ToolkitWindows::open('main', 300, 200, 'main');

    expect($window)->toBeInstanceOf(FakeWindow::class)
        ->and(ToolkitAdapters::driver()->windows())->toBe(['main' => $window])
        ->and(ToolkitAdapters::windows()->open('second', 10, 10))->toBeInstanceOf(FakeWindow::class);
});

it('refuses a registered toolkit whose driver does not open windows', function (): void {
    ToolkitAdapters::extend('plain', fn () => new stdClass());
    config(['toolkits.default' => 'plain']);

    expect(fn () => ToolkitWindows::open('main', 10, 10))->toThrow(RuntimeException::class, 'stdClass does not open windows.');
});

it('resolves the adapter manager by its class', function (): void {
    expect(app(ToolkitAdapterManager::class))->toBe(app('toolkit-adapters'));
});
