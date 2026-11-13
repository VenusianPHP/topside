<?php

declare(strict_types=1);

use Surface\Contracts\Rendering\Raycasting\RaycastException;
use Surface\Contracts\Rendering\RenderingException;
use Surface\VelvetGE\Drivers\Raycasting\CLandRaycastingDriver;
use Surface\VelvetGE\Drivers\Raycasting\PHPLandRaycastingDriver;

/** A stand-in for a library binding's driver: the PHP casting under another name. */
function libraryCaster(string $name): PHPLandRaycastingDriver
{
    return new class($name) extends PHPLandRaycastingDriver {
        public function __construct(private readonly string $name) {}

        public function driver(): string
        {
            return $this->name;
        }
    };
}

it('defaults to auto: ext when ext-raycast is loaded, native when not, built once', function (): void {
    $manager = raycasters();

    expect($manager->getDefaultDriver())->toBe('auto')
        ->and($manager->driver())->toBeInstanceOf(CLandRaycastingDriver::available() ? CLandRaycastingDriver::class : PHPLandRaycastingDriver::class)
        ->and($manager->driver('auto'))->toBe($manager->driver());
});

it('takes a registered library binding before ext and native', function (): void {
    expect(raycasters()->extend('embree', fn () => libraryCaster('embree'))->driver()->driver())->toBe('embree');
});

it('passes over drivers that cannot run here, down to native', function (): void {
    $missing = fn () => throw RaycastException::extensionMissing('9.9.9');
    $manager = raycasters()->extend('embree', $missing)->extend('ext', $missing);

    expect($manager->driver()->driver())->toBe('native');
});

it('names every driver and its reason when none can run', function (): void {
    $missing = fn () => throw RaycastException::extensionMissing('9.9.9');
    $manager = raycasters()->extend('ext', $missing)->extend('native', $missing);

    expect(fn () => $manager->driver())->toThrow(RenderingException::class, "'ext' (The 'ext' raycasting driver needs ext-raycast 9.9.9");
});

it('builds native when named, and takes its default from config', function (): void {
    expect(raycasters()->driver('native'))->toBeInstanceOf(PHPLandRaycastingDriver::class)
        ->and(raycasters(['rendering.raycaster.default' => 'native'])->driver()->driver())->toBe('native');
});

it('builds the ext driver when ext-raycast is loaded', function (): void {
    expect(raycasters()->driver('ext')->driver())->toBe('ext');
})->skip(! CLandRaycastingDriver::available(), 'ext-raycast is not loaded in this PHP.');

it('refuses the ext driver without ext-raycast, naming the extension and the PHP', function (): void {
    expect(fn () => raycasters()->driver('ext'))->toThrow(RaycastException::class, 'needs ext-raycast 0.11.0 or newer loaded in this PHP ('.PHP_BINARY.')');
})->skip(CLandRaycastingDriver::available(), 'ext-raycast is loaded in this PHP.');
