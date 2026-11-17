<?php

declare(strict_types=1);

use Surface\Contracts\Rendering\Framebuffers\DamageGranularity;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Painting\Painter;
use Surface\Contracts\Rendering\Recording\Queue;
use Surface\Contracts\Rendering\Recording\ShapeQueue;
use Surface\Contracts\Rendering\RenderingException;
use Surface\NutsAndBolts\Color;
use Surface\Rendering\Recording\Drivers\CLandRecordingDriver;
use VelvetGE\Painting\VelvetPainter;
use Surface\Rendering\Recording\Queues\PHPLandQueue;
use Surface\Rendering\Recording\DrawCommandQueue;
use Surface\Rendering\Recording\Drivers\QueueRecordingDriver;

/** A recording driver over a queue a test made, as a library package would write one. */
function queueDriver(Closure $queue): QueueRecordingDriver
{
    return new class($queue) extends QueueRecordingDriver {
        public function __construct(private Closure $make) {}

        public function driver(): string
        {
            return 'test';
        }

        public function queue(): Queue
        {
            return ($this->make)();
        }
    };
}

it('takes c when ext-pixel-queue is loaded and php otherwise, a library binding first', function (): void {
    $auto = recorders()->driver();
    expect($auto->driver())->toBe(extension_loaded('pixel_queue') ? 'c' : 'php')
        ->and(recorders()->driver('php')->driver())->toBe('php')
        ->and(recorders(['rendering.recorder.default' => 'php'])->driver()->driver())->toBe('php');

    $library = recorders()->extend('library', fn () => queueDriver(fn () => new PHPLandQueue()));
    expect($library->driver()->driver())->toBe('test');

    $refusing = recorders()->extend('library', fn () => throw new RenderingException('ext-library is not loaded.'));
    expect($refusing->driver()->driver())->toBe(extension_loaded('pixel_queue') ? 'c' : 'php');
});

it('refuses to make the c driver without ext-pixel-queue', function (): void {
    if (extension_loaded('pixel_queue')) {
        $this->markTestSkipped('ext-pixel-queue is loaded in this PHP.');
    }

    expect(fn () => new CLandRecordingDriver())->toThrow(RenderingException::class);
});

it('gives each painter a recorder of its own', function (): void {
    $driver = recorders()->driver('php');
    $first = $driver->recorder();
    $second = $driver->recorder();
    $first->begin(new Region(0, 0, 8, 8));

    expect($first)->not->toBe($second)
        ->and($first->drawing())->toBeTrue()
        ->and($second->drawing())->toBeFalse();
});
