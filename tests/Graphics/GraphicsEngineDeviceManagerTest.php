<?php

declare(strict_types=1);

use Surface\Contracts\Graphics\ActorStage;
use Surface\Contracts\Graphics\Backend;
use Surface\Contracts\Graphics\DrawingSurface;
use Surface\Contracts\Graphics\Feature;
use Surface\Contracts\Graphics\GraphicsException;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Graphics\GfxDevice;

/** An engine that makes no surfaces, which counts its release() calls. */
function fakeEngine(string $name, Backend $backend): GfxDevice
{
    return new class($name, $backend) extends GfxDevice {
        public int $released = 0;

        public function __construct(private readonly string $called, Backend $backend)
        {
            parent::__construct($backend);
        }

        public function name(): string
        {
            return $this->called;
        }

        public function has(Feature $feature): bool
        {
            return false;
        }

        public function surface(ActorStage $stage, ?FormatSpec $format = null): DrawingSurface
        {
            throw new LogicException('The manager tests make no surfaces.');
        }

        public function offscreen(int $width, int $height, ?FormatSpec $format = null): DrawingSurface
        {
            throw new LogicException('The manager tests make no surfaces.');
        }

        public function release(): void
        {
            $this->released++;
        }
    };
}

it('makes the engine a package registered, for the backend asked', function () {
    $gfx = gfx()->extend('both', fn (Backend $b) => fakeEngine('both', $b), Backend::CPU, Backend::GPU);

    expect($gfx->engine('both', 'cpu')->backend())->toBe(Backend::CPU)
        ->and($gfx->engine('both', 'gpu')->backend())->toBe(Backend::GPU)
        ->and($gfx->engine('both', 'cpu')->name())->toBe('both');
});

it('keeps one engine a backend', function () {
    $gfx = gfx()->extend('both', fn (Backend $b) => fakeEngine('both', $b), Backend::CPU, Backend::GPU);

    expect($gfx->engine('both', 'cpu'))->toBe($gfx->engine('both', 'cpu'))
        ->and($gfx->engine('both', 'cpu'))->not->toBe($gfx->engine('both', 'gpu'));
});

it('takes the GPU for auto where the engine has one, and the CPU otherwise', function () {
    $gfx = gfx()
        ->extend('both', fn (Backend $b) => fakeEngine('both', $b), Backend::CPU, Backend::GPU)
        ->extend('soft', fn (Backend $b) => fakeEngine('soft', $b), Backend::CPU);

    expect($gfx->engine('both', 'auto')->backend())->toBe(Backend::GPU)
        ->and($gfx->engine('soft', 'auto')->backend())->toBe(Backend::CPU);
});

it('reads the default engine and backend from config', function () {
    $gfx = gfx(['graphics.engine.default' => 'both', 'graphics.engine.backend' => 'cpu'])
        ->extend('both', fn (Backend $b) => fakeEngine('both', $b), Backend::CPU, Backend::GPU);

    expect($gfx->engine()->name())->toBe('both')
        ->and($gfx->engine()->backend())->toBe(Backend::CPU)
        ->and($gfx->driver())->toBe($gfx->engine());
});

it('falls back to velvet and auto with no config', function () {
    $gfx = gfx()->extend('velvet', fn (Backend $b) => fakeEngine('velvet', $b), Backend::CPU);

    expect($gfx->engine()->name())->toBe('velvet')
        ->and($gfx->engine()->backend())->toBe(Backend::CPU);
});

it('refuses a backend the engine does not offer, naming what it does', function () {
    $gfx = gfx()->extend('soft', fn (Backend $b) => fakeEngine('soft', $b), Backend::CPU);

    expect(fn () => $gfx->engine('soft', 'gpu'))
        ->toThrow(GraphicsException::class, 'The [soft] engine has no gpu backend. It offers: cpu.');
});

it('refuses a name nothing registered, and a word that is no backend', function () {
    $gfx = gfx()->extend('soft', fn (Backend $b) => fakeEngine('soft', $b), Backend::CPU);

    expect(fn () => $gfx->engine('metal'))->toThrow(GraphicsException::class, 'No graphics engine is registered as [metal]. Registered: soft.')
        ->and(fn () => $gfx->engine('soft', 'fast'))->toThrow(GraphicsException::class, "[fast] is not a backend");
});

it('refuses an engine registered with no backend', function () {
    expect(fn () => gfx()->extend('none', fn (Backend $b) => fakeEngine('none', $b)))
        ->toThrow(GraphicsException::class, 'registered with no backend');
});

it('lists the engines and what each offers', function () {
    $gfx = gfx()
        ->extend('both', fn (Backend $b) => fakeEngine('both', $b), Backend::CPU, Backend::GPU)
        ->extend('soft', fn (Backend $b) => fakeEngine('soft', $b), Backend::CPU);

    expect($gfx->engines())->toBe(['both', 'soft'])
        ->and($gfx->backends('both'))->toBe([Backend::CPU, Backend::GPU])
        ->and($gfx->backends('metal'))->toBe([]);
});

it('releases every engine it made when they are forgotten, and makes new ones after', function () {
    $gfx = gfx()->extend('soft', fn (Backend $b) => fakeEngine('soft', $b), Backend::CPU);
    $first = $gfx->engine('soft');

    $gfx->forgetDrivers();

    expect($first->released)->toBe(1)
        ->and($gfx->engine('soft'))->not->toBe($first);
});
