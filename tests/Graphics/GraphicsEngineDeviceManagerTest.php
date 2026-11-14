<?php

declare(strict_types=1);

use Surface\Contracts\Graphics\Backend;
use Surface\Contracts\Graphics\GraphicsException;
use Surface\Contracts\Graphics\Resource;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Graphics\GEFramebuffer;
use Surface\Graphics\GraphicsEngine;
use Surface\Graphics\GraphicsEngineDeviceManager;

/** An engine with a framebuffer and nothing else, which counts its release() calls. */
function fakeEngine(string $name, Backend $backend): GraphicsEngine
{
    return new class($name, $backend) extends GraphicsEngine {
        public int $released = 0;

        public function __construct(private readonly string $called, Backend $backend)
        {
            parent::__construct($backend);
        }

        public function name(): string
        {
            return $this->called;
        }

        public function supports(Resource $resource): bool
        {
            return $resource === Resource::FRAMEBUFFER;
        }

        public function framebuffer(int $width, int $height, ?FormatSpec $format = null): GEFramebuffer
        {
            return new class($this) extends GEFramebuffer {
                public function accepts(GEFramebuffer $other): void
                {
                    $this->mine($other);
                }

                public function width(): int
                {
                    return 4;
                }

                public function height(): int
                {
                    return 4;
                }

                public function format(): FormatSpec
                {
                    return FormatSpec::rgba8();
                }

                public function clear(Surface\NutsAndBolts\Color $color): static
                {
                    return $this;
                }

                protected function readRgba8(Surface\Contracts\Rendering\Framebuffers\Region $region): string
                {
                    return '';
                }

                protected function writeRgba8(string $rgba8, Surface\Contracts\Rendering\Framebuffers\Region $region): void {}
            };
        }

        public function release(): void
        {
            $this->released++;
        }
    };
}

/** A graphics manager reading the given config, without a container. */
function gfx(array $config = []): GraphicsEngineDeviceManager
{
    return new class($config) extends GraphicsEngineDeviceManager {
        public function __construct(array $config)
        {
            $this->config = fakeConfig($config);
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

it('throws for a resource the engine does not have', function () {
    $engine = fakeEngine('soft', Backend::CPU);

    expect($engine->supports(Resource::PAINTER))->toBeFalse()
        ->and(fn () => $engine->rasterizer())->toThrow(GraphicsException::class, 'The [soft] engine has no rasterizer.')
        ->and(fn () => $engine->painter($engine->framebuffer(4, 4)))->toThrow(GraphicsException::class, 'The [soft] engine has no painter.');
});

it('keeps an engine\'s resources to that engine', function () {
    [$a, $b] = [fakeEngine('a', Backend::CPU), fakeEngine('b', Backend::CPU)];
    $frame = $a->framebuffer(4, 4);

    $frame->accepts($a->framebuffer(4, 4));

    expect($frame->engine())->toBe($a)
        ->and(fn () => $frame->accepts($b->framebuffer(4, 4)))->toThrow(GraphicsException::class, 'A resource of the [a] engine was given one of the [b] engine');
});
