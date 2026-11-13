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
use Surface\VelvetGE\Drivers\Recording\CLandRecordingDriver;
use Surface\VelvetGE\Drivers\Recording\QueueRecordingDriver;
use Surface\VelvetGE\Painters\VelvetPainter;
use Surface\VelvetGE\Recording\PHPLandQueue;
use Surface\VelvetGE\Recording\VelvetRecorder;

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

it('takes ext when ext-pixel-queue is loaded and native otherwise, a library binding first', function (): void {
    $auto = recorders()->driver();
    expect($auto->driver())->toBe(extension_loaded('pixel_queue') ? 'ext' : 'native')
        ->and(recorders()->driver('native')->driver())->toBe('native')
        ->and(recorders(['rendering.recorder.default' => 'native'])->driver()->driver())->toBe('native');

    $library = recorders()->extend('library', fn () => queueDriver(fn () => new PHPLandQueue()));
    expect($library->driver()->driver())->toBe('test');

    $refusing = recorders()->extend('library', fn () => throw new RenderingException('ext-library is not loaded.'));
    expect($refusing->driver()->driver())->toBe(extension_loaded('pixel_queue') ? 'ext' : 'native');
});

it('refuses to make the ext driver without ext-pixel-queue', function (): void {
    if (extension_loaded('pixel_queue')) {
        $this->markTestSkipped('ext-pixel-queue is loaded in this PHP.');
    }

    expect(fn () => new CLandRecordingDriver())->toThrow(RenderingException::class);
});

it('gives each painter a recorder of its own', function (): void {
    $driver = recorders()->driver('native');
    $first = $driver->recorder();
    $second = $driver->recorder();
    $first->begin(new Region(0, 0, 8, 8));

    expect($first)->not->toBe($second)
        ->and($first->drawing())->toBeTrue()
        ->and($second->drawing())->toBeFalse();
});

it('is a whole recorder over a queue that records no shape itself', function (): void {
    // every call counted, and passed to VelvetGE's queue in PHP
    $calls = new ArrayObject();
    $plain = new class($calls) implements Queue {
        private PHPLandQueue $inner;

        public function __construct(private ArrayObject $calls)
        {
            $this->inner = new PHPLandQueue();
        }

        public function __call(string $method, array $arguments): mixed
        {
            return null;
        }

        public function begin(Region $surface): void
        {
            $this->inner->begin($surface);
        }

        public function recording(): bool
        {
            return $this->inner->recording();
        }

        public function abandon(): void
        {
            $this->inner->abandon();
        }

        public function finish(): array
        {
            return $this->inner->finish();
        }

        public function push(): void
        {
            $this->inner->push();
        }

        public function pop(): void
        {
            $this->inner->pop();
        }

        public function depth(): int
        {
            return $this->inner->depth();
        }

        public function compose(Surface\NutsAndBolts\Affine $matrix): void
        {
            $this->inner->compose($matrix);
        }

        public function matrix(): Surface\NutsAndBolts\Affine
        {
            return $this->inner->matrix();
        }

        public function clip(Region $clip): void
        {
            $this->inner->clip($clip);
        }

        public function clipRegion(): Region
        {
            return $this->inner->clipRegion();
        }

        public function append(array $command): void
        {
            $this->calls[] = $command[0];
            $this->inner->append($command);
        }

        public function enter(Region $clip): void
        {
            $this->inner->enter($clip);
        }

        public function leave(): array
        {
            return $this->inner->leave();
        }

        public function drawn(Region $surface, DamageGranularity $granularity, array $also = []): array
        {
            return $this->inner->drawn($surface, $granularity, $also);
        }

        public function changed(Region $surface, DamageGranularity $granularity, array $also = []): array
        {
            return $this->inner->changed($surface, $granularity, $also);
        }
    };
    $scene = function (Painter $g): void {
        $g->clear(Color::rgb(0, 0, 0))->fillRect(2, 2, 6, 4, Color::rgb(255, 0, 0))->fillEllipse(20, 12, 6, 4, Color::rgb(0, 255, 0))
            ->roundRect(4, 14, 20, 8, [2, 2, 2, 2], Color::rgb(0, 0, 255), Color::rgb(255, 255, 255), 1.0)->line(0, 0, 30, 20, Color::rgb(255, 255, 0), 2.0);
    };

    $library = new RecordingEngine(32, 24, queueDriver(fn () => $plain)->recorder());
    $velvet = new RecordingEngine(32, 24, new VelvetRecorder(new PHPLandQueue()));

    expect($plain)->not->toBeInstanceOf(ShapeQueue::class)
        ->and($library->commandsOf($scene))->toEqual($velvet->commandsOf($scene))
        ->and($calls->getArrayCopy())->toBe(['clear', 'path', 'ellipse', 'roundrect', 'roundring', 'polyline']);
});

it('paints the same pixels and reports the same damage whichever queue records the frames', function (): void {
    needsPixelQueue();
    $results = [];
    foreach (['native', 'ext'] as $name) {
        $buffer = nativeFull(FormatSpec::rgba8(), QUEUE_W, QUEUE_H);
        $image = nativeFull(FormatSpec::rgba8(), 24, 16);
        $painter = new VelvetPainter($buffer, new Surface\VelvetGE\Drivers\Rasterization\PHPLandRasterizationDriver(), null, true, null, recorders()->driver($name)->recorder());
        $damage = [];
        foreach ([[3, 30], [3, 30], [3, 31], [4, 30]] as [$seed, $calls]) {
            $log = [];
            $painter->frame(function (Painter $g) use ($seed, $calls, $image, &$log): void {
                queueScene($g, $seed, $image, $log, false, $calls);
            });
            $damage[] = queueRegions($painter->damage());
        }
        $results[$name] = [md5($buffer->flushRegion(new Region(0, 0, QUEUE_W, QUEUE_H), FormatSpec::rgba8())), $damage];
    }

    expect($results['ext'])->toBe($results['native'])
        // the second frame is the first again: only what never compares equal (an image) is drawn again, not the surface
        ->and($results['native'][1][1])->not->toBe([[0, 0, QUEUE_W, QUEUE_H]]);
});
