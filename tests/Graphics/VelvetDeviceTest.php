<?php

declare(strict_types=1);

use Surface\Contracts\Graphics\Backend;
use Surface\Contracts\Graphics\GraphicsException;
use Surface\Contracts\Graphics\Resource;
use Surface\Contracts\Graphics\StrokeStyle;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Graphics\GEFramebuffer;
use Surface\Graphics\GEPainter;
use Surface\Graphics\GERasterizer;
use Surface\Graphics\GERaycaster;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\Path;
use Surface\VelvetGE\VelvetDevice;
use Venusian\Topside\Tests\Support\Framebuffers\Formats;

/*
 * Velvet as a graphics engine: what a developer gets from it is the GE type, and what they draw
 * through it is what Rendering draws underneath.
 */

/** Velvet on the CPU over the given Rendering drivers, without a container. */
function velvet(bool $raycasting = false, string $driver = 'native'): VelvetDevice
{
    return new VelvetDevice(
        Backend::CPU,
        framebuffers(['rendering.framebuffer.default' => $driver]),
        rasterize(['rendering.rasterizer.default' => $driver]),
        painters(['rendering.painter.default' => $driver]),
        $raycasting ? raycasters(['rendering.raycaster.default' => $driver]) : null,
    );
}

function box(float $x, float $y, float $w, float $h): Path
{
    return (new Path)->moveTo($x, $y)->lineTo($x + $w, $y)->lineTo($x + $w, $y + $h)->lineTo($x, $y + $h)->close();
}

/** The RGBA8 of one pixel, as hex. */
function pixelOf(GEFramebuffer $frame, int $x, int $y): string
{
    return bin2hex($frame->read(new Region($x, $y, 1, 1)));
}

function velvetRoom(): Surface\Contracts\Rendering\Raycasting\Grid
{
    return new Surface\Contracts\Rendering\Raycasting\Grid(5, 5, "\x01\x01\x01\x01\x01"."\x01\x00\x00\x00\x02"."\x01\x00\x00\x00\x02"."\x01\x00\x00\x00\x02"."\x01\x03\x03\x03\x01");
}

function velvetCamera(): Surface\Contracts\Rendering\Raycasting\Camera
{
    return new Surface\Contracts\Rendering\Raycasting\Camera(2.5, 2.5, 0.7, 1.1);
}

function velvetScenery(): Surface\Contracts\Rendering\Raycasting\Scenery
{
    return new Surface\Contracts\Rendering\Raycasting\Scenery([1 => 0xC04020FF, 2 => 0x2040C0FF, 3 => 0x20C040FF], 0x111111FF, 0x222222FF, 160);
}

dataset('velvet drivers', fn () => extension_loaded('fb') && extension_loaded('rasterize') && extension_loaded('paint') ? ['native', 'ext'] : ['native']);

it('is velvet on the CPU, with a raycaster only when raycasting is on', function () {
    expect(velvet()->name())->toBe('velvet')
        ->and(velvet()->backend())->toBe(Backend::CPU)
        ->and(velvet()->supports(Resource::FRAMEBUFFER))->toBeTrue()
        ->and(velvet()->supports(Resource::RASTERIZER))->toBeTrue()
        ->and(velvet()->supports(Resource::PAINTER))->toBeTrue()
        ->and(velvet()->supports(Resource::RAYCASTER))->toBeFalse()
        ->and(velvet(raycasting: true)->supports(Resource::RAYCASTER))->toBeTrue();
});

it('refuses the GPU backend, which is not built', function () {
    expect(fn () => new VelvetDevice(Backend::GPU, framebuffers(), rasterize(), painters()))
        ->toThrow(GraphicsException::class, "The [velvet] engine's gpu backend is not built yet.");

    $gfx = gfx()->extend('velvet', fn (Backend $b) => velvet(), Backend::CPU)->notReady('velvet', Backend::GPU);

    expect(fn () => $gfx->engine('velvet', 'gpu'))->toThrow(GraphicsException::class, "The [velvet] engine's gpu backend is not built yet.")
        ->and($gfx->engine('velvet', 'auto')->backend())->toBe(Backend::CPU);
});

it('hands out GE types', function () {
    $engine = velvet(raycasting: true);
    $frame = $engine->framebuffer(8, 8);

    expect($frame)->toBeInstanceOf(GEFramebuffer::class)
        ->and($engine->rasterizer())->toBeInstanceOf(GERasterizer::class)
        ->and($engine->painter($frame))->toBeInstanceOf(GEPainter::class)
        ->and($engine->raycaster(velvetRoom()))->toBeInstanceOf(GERaycaster::class)
        ->and($frame->engine())->toBe($engine);
});

it('makes a framebuffer of the size and format asked, RGBA8 unasked', function (string $driver) {
    $engine = velvet(driver: $driver);

    expect($engine->framebuffer(20, 10)->width())->toBe(20)
        ->and($engine->framebuffer(20, 10)->height())->toBe(10)
        ->and($engine->framebuffer(20, 10)->format())->toEqual(FormatSpec::rgba8())
        ->and($engine->framebuffer(20, 10, Formats::rgb565())->format())->toEqual(Formats::rgb565())
        ->and(fn () => $engine->framebuffer(0, 10))->toThrow(GraphicsException::class, 'A framebuffer of 0x10 cannot be made');
})->with('velvet drivers');

it('reads back as RGBA8 what was written and cleared, whatever the format', function (string $driver, FormatSpec $format) {
    $frame = velvet(driver: $driver)->framebuffer(6, 4, $format);
    $frame->clear(Color::rgb(255, 0, 0));
    $frame->write(str_repeat("\x00\xff\x00\xff", 6), new Region(1, 2, 3, 2));

    expect(pixelOf($frame, 0, 0))->toBe('ff0000ff')
        ->and(pixelOf($frame, 1, 2))->toBe('00ff00ff')
        ->and(pixelOf($frame, 3, 3))->toBe('00ff00ff')
        ->and(pixelOf($frame, 4, 3))->toBe('ff0000ff')
        ->and(strlen($frame->read()))->toBe(6 * 4 * 4)
        ->and(substr($frame->read(), (2 * 6 + 1) * 4, 4))->toBe("\x00\xff\x00\xff")
        ->and(bin2hex($frame->read(new Region(0, 2, 2, 1))))->toBe('ff0000ff'.'00ff00ff');
})->with('velvet drivers')->with([FormatSpec::rgba8(), FormatSpec::bgra8(), Formats::rgb565()]);

it('refuses a region outside the surface and bytes of the wrong size', function () {
    $frame = velvet()->framebuffer(6, 4);

    expect(fn () => $frame->read(new Region(4, 0, 3, 1)))->toThrow(GraphicsException::class, 'A region of 3x1 at (4, 0) is not inside a 6x4 surface.')
        ->and(fn () => $frame->write('abcd', new Region(0, 0, 2, 1)))->toThrow(GraphicsException::class, '4 bytes were given for a 2x1 region: RGBA8 takes 8.');
});

it('fills and strokes a path at once with the rasterizer, inside the clip', function (string $driver) {
    $engine = velvet(driver: $driver);
    $frame = $engine->framebuffer(16, 16)->clear(Color::rgb(0, 0, 0));
    $hard = $engine->rasterizer(Edges::HARD);

    $hard->fill($frame, box(2, 2, 6, 6), Color::rgb(255, 0, 0));
    $hard->fill($frame, box(0, 10, 16, 4), Color::rgb(0, 0, 255), clip: new Region(4, 0, 4, 16));
    $hard->stroke($frame, (new Path)->moveTo(10, 3)->lineTo(15, 3), Color::rgb(0, 255, 0), new StrokeStyle(2.0));

    expect($hard->edges())->toBe(Edges::HARD)
        ->and(pixelOf($frame, 2, 2))->toBe('ff0000ff')
        ->and(pixelOf($frame, 7, 7))->toBe('ff0000ff')
        ->and(pixelOf($frame, 8, 8))->toBe('000000ff')
        ->and(pixelOf($frame, 3, 11))->toBe('000000ff')
        ->and(pixelOf($frame, 4, 11))->toBe('0000ffff')
        ->and(pixelOf($frame, 8, 11))->toBe('000000ff')
        ->and(pixelOf($frame, 12, 2))->toBe('00ff00ff')
        ->and(pixelOf($frame, 12, 3))->toBe('00ff00ff')
        ->and(pixelOf($frame, 12, 4))->toBe('000000ff');
})->with('velvet drivers');

it('draws a frame with the painter: the same pixels the rasterizer fills', function (string $driver) {
    $engine = velvet(driver: $driver);
    [$painted, $filled] = [$engine->framebuffer(24, 16), $engine->framebuffer(24, 16)];
    $painter = $engine->painter($painted);

    $returned = $painter->frame(function (GEPainter $g) use ($painter) {
        expect($g)->toBe($painter);
        $g->clear(Color::rgb(10, 20, 30));
        $g->push()->translate(2, 1)->fillRect(1.5, 1.5, 9, 7, Color::rgba(255, 128, 0, 0.6))->pop();
    });
    $filled->clear(Color::rgb(10, 20, 30));
    $engine->rasterizer()->fill($filled, box(3.5, 2.5, 9, 7), Color::rgba(255, 128, 0, 0.6));

    expect($returned)->toBe($painter)
        ->and($painter->framebuffer())->toBe($painted)
        ->and($painter->width())->toBe(24)
        ->and(bin2hex($painted->read()))->toBe(bin2hex($filled->read()));
})->with('velvet drivers');

it('redraws only what changed in the next frame', function () {
    $engine = velvet();
    $painter = $engine->painter($engine->framebuffer(32, 16));
    $scene = fn (int $x) => fn (GEPainter $g) => $g->clear(Color::rgb(0, 0, 0))->fillRect($x, 4, 4, 4, Color::rgb(255, 255, 255));

    $painter->frame($scene(2));
    $painter->frame($scene(20));
    $changed = $painter->damage();
    $painter->frame($scene(20));

    expect($changed)->not->toBe([])
        ->and(array_sum(array_map(fn (Region $r) => $r->width * $r->height, $changed)))->toBeLessThan(32 * 16)
        ->and($painter->damage())->toBe([]);
});

it('paints one of its framebuffers as an image, and a layer with the same painter', function () {
    $engine = velvet();
    $stamp = $engine->framebuffer(2, 2)->clear(Color::rgb(0, 255, 0));
    $frame = $engine->framebuffer(12, 8);
    $painter = $engine->painter($frame);

    $painter->frame(function (GEPainter $g) use ($stamp, $painter) {
        $g->clear(Color::rgb(0, 0, 0));
        $g->image($stamp, 3, 1);
        $g->layer(6, 4, 4, 2, function (GEPainter $inside) use ($painter) {
            expect($inside)->toBe($painter);
            $inside->fillRect(6, 4, 4, 2, Color::rgb(255, 0, 0));
        });
    });

    expect(pixelOf($frame, 3, 1))->toBe('00ff00ff')
        ->and(pixelOf($frame, 4, 2))->toBe('00ff00ff')
        ->and(pixelOf($frame, 5, 1))->toBe('000000ff')
        ->and(pixelOf($frame, 7, 5))->toBe('ff0000ff');
});

it('paints a view with the raycaster: what Rendering\'s raycaster paints', function (string $driver) {
    $engine = velvet(raycasting: true, driver: $driver);
    $frame = $engine->framebuffer(48, 32);
    $direct = framebuffers(['rendering.framebuffer.default' => $driver])->driver()->dirty(FormatSpec::rgba8(), 48, 32);
    $where = new Region(8, 4, 32, 24);

    $ray = $engine->raycaster(velvetRoom());
    $ray->paint(velvetCamera(), velvetScenery(), $frame, $where);
    raycasters(['rendering.raycaster.default' => $driver])->driver()->caster(velvetRoom())->paint(velvetCamera(), velvetScenery(), $direct, $where);

    expect($ray->grid())->toEqual(velvetRoom())
        ->and(bin2hex($frame->read()))->toBe(bin2hex($direct->toRgba8()))
        ->and(fn () => $ray->paint(velvetCamera(), velvetScenery(), $frame, new Region(40, 0, 16, 8)))->toThrow(GraphicsException::class, 'is not inside a 48x32 surface');
})->with(fn () => extension_loaded('fb') && extension_loaded('raycast') ? ['native', 'ext'] : ['native']);

it('casts a ray and a fan of rays: what Rendering\'s raycaster answers', function (string $driver) {
    $ray = velvet(raycasting: true, driver: $driver)->raycaster(velvetRoom());
    $direct = raycasters(['rendering.raycaster.default' => $driver])->driver()->caster(velvetRoom());

    expect($ray->cast(2.5, 2.5, 0.0))->toEqual($direct->cast(2.5, 2.5, 0.0))
        ->and($ray->cast(2.5, 2.5, 0.0)?->wall)->toBe(2)
        ->and($ray->cast(2.5, 2.5, 0.0, 0.5))->toBeNull()
        ->and(bin2hex($ray->fan(velvetCamera(), 16)))->toBe(bin2hex($direct->fan(velvetCamera(), 16)));
})->with(fn () => extension_loaded('raycast') ? ['native', 'ext'] : ['native']);

it('uses the driver named for a part, and Rendering\'s default for a part left unnamed', function () {
    $engine = fn (array $drivers) => new VelvetDevice(Backend::CPU, framebuffers(['rendering.framebuffer.default' => 'native']), rasterize(), painters(), null, $drivers);
    $store = fn (VelvetDevice $velvet) => $velvet->framebuffer(4, 4)->inner()->memory();

    // the native driver keeps its pixels in PHP, so it has no memory to point at; ext-fb's has
    expect($store($engine([])))->toBeNull()
        ->and($store($engine(['framebuffer' => null])))->toBeNull()
        ->and($store($engine(['framebuffer' => 'ext'])))->not->toBeNull();
})->skip(! extension_loaded('fb'), 'needs ext-fb as a second framebuffer driver');

it('names the driver behind each part', function () {
    expect(velvet()->modules())->toBe(['framebuffer' => 'native', 'rasterizer' => 'native', 'painter' => 'native'])
        ->and(velvet(raycasting: true)->modules()['raycaster'])->toBe('native');
});

it('refuses a driver name nothing registered', function () {
    $velvet = new VelvetDevice(Backend::CPU, framebuffers(), rasterize(), painters(), null, ['framebuffer' => 'nope']);

    expect(fn () => $velvet->framebuffer(4, 4))->toThrow(InvalidArgumentException::class, 'nope');
});

it('says how to turn raycasting on when it is off', function () {
    expect(fn () => velvet()->raycaster(velvetRoom()))
        ->toThrow(GraphicsException::class, 'The [velvet] engine has no raycaster. Turn raycasting on');
});

it('keeps another engine\'s framebuffer out of its painter, rasterizer and raycaster', function () {
    [$mine, $other] = [velvet(raycasting: true), velvet()];
    $theirs = $other->framebuffer(8, 8);
    $own = $mine->framebuffer(8, 8);

    expect(fn () => $mine->painter($theirs))->toThrow(GraphicsException::class, 'an engine\'s resources work only with each other')
        ->and(fn () => $mine->rasterizer()->fill($theirs, box(0, 0, 2, 2), Color::rgb(1, 1, 1)))->toThrow(GraphicsException::class)
        ->and(fn () => $mine->raycaster(velvetRoom())->paint(velvetCamera(), velvetScenery(), $theirs))->toThrow(GraphicsException::class)
        ->and(fn () => $mine->painter($own)->frame(fn (GEPainter $g) => $g->image($theirs, 0, 0)))->toThrow(GraphicsException::class);
});

/** A stage of a fixed size that keeps what it was last shown. */
function fakeStage(int $width, int $height, float $scale = 1.0): Surface\Contracts\Graphics\ActorStage
{
    return new class($width, $height, $scale) implements Surface\Contracts\Graphics\ActorStage {
        /** @var array{string, int, int}|null */
        public ?array $shown = null;

        public function __construct(public int $width, public int $height, private readonly float $scale) {}

        public function pixelSize(): array
        {
            return [$this->width, $this->height];
        }

        public function pixelScale(): float
        {
            return $this->scale;
        }

        public function display(string $rgba8, int $width, int $height): void
        {
            $this->shown = [$rgba8, $width, $height];
        }

        public function nativeSurface(): ?object
        {
            return null;
        }
    };
}

it('attaches a framebuffer of the stage\'s size, and presents its pixels on the stage', function (string $driver, FormatSpec $format) {
    $engine = velvet(driver: $driver);
    $stage = fakeStage(12, 6);
    $screen = $engine->attach($stage, $format);

    $engine->painter($screen)->frame(fn (GEPainter $g) => $g->clear(Color::rgb(0, 0, 255))->fillRect(0, 0, 4, 6, Color::rgb(255, 0, 0)));
    $returned = $screen->present();

    expect($screen->width())->toBe(12)
        ->and($screen->height())->toBe(6)
        ->and($screen->stage())->toBe($stage)
        ->and($returned)->toBe($screen)
        ->and($stage->shown[1])->toBe(12)
        ->and($stage->shown[2])->toBe(6)
        ->and(bin2hex($stage->shown[0]))->toBe(bin2hex($screen->read()))
        ->and(bin2hex(substr($stage->shown[0], 0, 4)))->toBe('ff0000ff')
        ->and(bin2hex(substr($stage->shown[0], 4 * 4, 4)))->toBe('0000ffff');
})->with('velvet drivers')->with([FormatSpec::rgba8(), FormatSpec::bgra8(), Formats::rgb565()]);

it('keeps the size it attached with: a resized stage is attached to again', function () {
    $engine = velvet();
    $stage = fakeStage(8, 4);
    $screen = $engine->attach($stage);
    $stage->width = 20;

    expect($screen->width())->toBe(8)
        ->and($engine->attach($stage)->width())->toBe(20);
});

it('refuses a stage with no size, and a present with no stage', function () {
    $engine = velvet();

    expect(fn () => $engine->attach(fakeStage(0, 0)))->toThrow(GraphicsException::class, 'The stage has no size yet')
        ->and(fn () => $engine->framebuffer(4, 4)->present())->toThrow(GraphicsException::class, 'This framebuffer has no stage to present on')
        ->and($engine->framebuffer(4, 4)->stage())->toBeNull();
});

/** A paced stage whose display is due a frame when refresh() is called. */
function pacedStage(int $width, int $height): Surface\Contracts\Graphics\PacedStage
{
    return new class($width, $height) implements Surface\Contracts\Graphics\PacedStage {
        /** @var list<string> the pixels of every frame shown */
        public array $frames = [];

        public int $asked = 0;

        private ?Closure $due = null;

        public function __construct(public int $width, public int $height) {}

        public function pixelSize(): array
        {
            return [$this->width, $this->height];
        }

        public function pixelScale(): float
        {
            return 1.0;
        }

        public function display(string $rgba8, int $width, int $height): void
        {
            $this->frames[] = $rgba8;
        }

        public function nativeSurface(): ?object
        {
            return null;
        }

        public function whenDue(Closure $show): void
        {
            $this->asked++;
            $this->due = $show;
        }

        public function refresh(): void
        {
            [$show, $this->due] = [$this->due, null];
            $show?->__invoke();
        }
    };
}

it('shows nothing on a paced stage until its display is due a frame, then the latest frame, once', function () {
    $stage = pacedStage(4, 2);
    $screen = velvet()->attach($stage);

    foreach ([Color::rgb(255, 0, 0), Color::rgb(0, 255, 0), Color::rgb(0, 0, 255)] as $color) {
        $screen->clear($color)->present();
    }
    expect($stage->frames)->toBe([])->and($stage->asked)->toBe(1)->and($screen->shown())->toBe(0);

    // drawn again after the last present(): what is shown is what it holds when the display is due
    $screen->clear(Color::rgb(9, 8, 7));
    $stage->refresh();
    expect($stage->frames)->toBe([str_repeat("\x09\x08\x07\xFF", 8)])->and($screen->shown())->toBe(1);

    // nothing presented since: a refresh shows nothing more
    $stage->refresh();
    expect($stage->frames)->toHaveCount(1);

    // and the next present() is shown at the refresh after it
    $screen->clear(Color::rgb(1, 2, 3))->present();
    $stage->refresh();
    expect($stage->frames)->toHaveCount(2)->and($stage->frames[1])->toBe(str_repeat("\x01\x02\x03\xFF", 8))
        ->and($stage->asked)->toBe(2)->and($screen->shown())->toBe(2);
});

it('counts every present() as shown on a stage that is not paced', function () {
    $screen = velvet()->attach(fakeStage(4, 2));
    $screen->present()->present()->present();

    expect($screen->shown())->toBe(3);
});
