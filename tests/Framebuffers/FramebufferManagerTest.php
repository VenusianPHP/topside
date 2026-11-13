<?php

declare(strict_types=1);

use Surface\Contracts\Rendering\Framebuffers\FramebufferException;
use Surface\Contracts\Rendering\RenderingException;
use Surface\VelvetGE\Drivers\Framebuffers\CLandFramebufferDriver;
use Surface\VelvetGE\Drivers\Framebuffers\PHPLandFramebufferDriver;

/** A stand-in for a library binding's driver: PHP bytes under another name. */
function libraryDriver(string $name): PHPLandFramebufferDriver
{
    return new class($name) extends PHPLandFramebufferDriver {
        public function __construct(private readonly string $name) {}

        public function driver(): string
        {
            return $this->name;
        }
    };
}

it('defaults to auto: ext when ext-fb 0.11.1 is loaded, native when not, built once', function (): void {
    $manager = framebuffers();

    expect($manager->getDefaultDriver())->toBe('auto')
        ->and($manager->driver())->toBeInstanceOf(CLandFramebufferDriver::available() ? CLandFramebufferDriver::class : PHPLandFramebufferDriver::class)
        ->and($manager->driver('auto'))->toBe($manager->driver());
});

it('takes a registered library binding before ext and native, whenever it was registered', function (): void {
    $after = framebuffers()->extend('pixman', fn () => libraryDriver('pixman'));

    expect($after->driver()->driver())->toBe('pixman');
});

it('takes library bindings in the order they were registered', function (): void {
    $manager = framebuffers()->extend('first', fn () => libraryDriver('first'))->extend('second', fn () => libraryDriver('second'));

    expect($manager->driver()->driver())->toBe('first');
});

it('passes over a binding that cannot run here, down to native', function (): void {
    $missing = fn () => throw FramebufferException::extensionMissing('pixman', '0.1.0');
    $manager = framebuffers()->extend('pixman', $missing)->extend('ext', $missing);

    expect($manager->driver()->driver())->toBe('native');
});

it('names every driver and its reason when none can run', function (): void {
    $missing = fn () => throw FramebufferException::extensionMissing('pixman', '0.1.0');
    $manager = framebuffers()->extend('pixman', $missing)->extend('ext', $missing)->extend('native', $missing);

    expect(fn () => $manager->driver())->toThrow(RenderingException::class, "'pixman' (");
});

it('does not hide a defect in a binding', function (): void {
    $manager = framebuffers()->extend('pixman', fn () => throw new TypeError('a bug'));

    expect(fn () => $manager->driver())->toThrow(TypeError::class, 'a bug');
});

it('builds native when named, whatever is loaded', function (): void {
    expect(framebuffers()->driver('native'))->toBeInstanceOf(PHPLandFramebufferDriver::class)
        ->and(framebuffers(['rendering.framebuffer.default' => 'native'])->driver()->driver())->toBe('native');
});

it('takes its default from config', function (): void {
    expect(framebuffers(['rendering.framebuffer.default' => 'ext'])->getDefaultDriver())->toBe('ext');
});

it('builds the ext driver when ext-fb is loaded', function (): void {
    expect(framebuffers()->driver('ext'))->toBeInstanceOf(CLandFramebufferDriver::class)
        ->and(framebuffers()->driver('ext')->driver())->toBe('ext');
})->skip(! CLandFramebufferDriver::available(), 'ext-fb 0.11.1 is not loaded in this PHP.');

it('refuses the ext driver without ext-fb 0.11.1, naming the extension and the PHP', function (): void {
    expect(fn () => framebuffers()->driver('ext'))->toThrow(FramebufferException::class, 'needs ext-fb 0.11.1 or newer loaded in this PHP ('.PHP_BINARY.')');
})->skip(CLandFramebufferDriver::available(), 'ext-fb 0.11.1 is loaded in this PHP.');

it('refuses a driver it does not have', function (): void {
    expect(fn () => framebuffers()->driver('gpu'))->toThrow(InvalidArgumentException::class, 'Driver [gpu] not supported');
});
