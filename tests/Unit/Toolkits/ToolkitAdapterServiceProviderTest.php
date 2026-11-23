<?php

declare(strict_types=1);

use Surface\Toolkits\ToolkitAdapters;
use Venusian\Topside\Tests\Fakes\BrokenProvider;
use Venusian\Topside\Tests\Fakes\FakeDriver;
use Venusian\Topside\Tests\Fakes\FakeProvider;

it('builds no driver when it registers', function (): void {
    expect(FakeDriver::$built)->toBe(0)
        ->and(ToolkitAdapters::adapters())->toBe([]);
});

it('merges its package config under the toolkit name', function (): void {
    expect(config('fake.answer'))->toBe(42);
});

it('keeps the app\'s own config over the package defaults', function (): void {
    topside(['fake' => ['answer' => 7]]);

    expect(config('fake.answer'))->toBe(7);
});

it('builds the driver the first time it is asked for, once', function (): void {
    $driver = ToolkitAdapters::driver('fake');

    expect($driver)->toBeInstanceOf(FakeDriver::class)
        ->and(ToolkitAdapters::driver('fake'))->toBe($driver)
        ->and(FakeDriver::$built)->toBe(1)
        ->and(ToolkitAdapters::adapters())->toBe(['fake' => $driver]);
});

it('boots with an installed toolkit that cannot run here', function (): void {
    topside(providers: [FakeProvider::class, BrokenProvider::class]);

    expect(ToolkitAdapters::driver())->toBeInstanceOf(FakeDriver::class)
        ->and(fn () => ToolkitAdapters::driver('broken'))->toThrow(RuntimeException::class, 'ext-broken missing.');
});

it('publishes its config file as config/{toolkit}.php under the {toolkit}-config tag', function (): void {
    expect(FakeProvider::pathsToPublish(FakeProvider::class, 'fake-config'))->toBe([
        realpath(__DIR__.'/../../Fakes/config/fake.php') => $this->app->configPath('fake.php'),
    ]);
});
