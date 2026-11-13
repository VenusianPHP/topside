<?php

declare(strict_types=1);

use Surface\Contracts\Graphics\DrawingException;
use Surface\Contracts\Graphics\GraphicsEngine as GraphicsEngineContract;
use Surface\Contracts\Graphics\Part;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Framebuffers\PagedFramebuffer;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Framebuffers\RingFramebuffer;
use Surface\Contracts\Rendering\Painting\Painter;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Contracts\Rendering\Rasterization\Rasterizer;
use Surface\Contracts\Rendering\Rasterization\ShapeRasterizer;
use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Graphics\GraphicsEngine;
use Surface\Graphics\GraphicsManager;
use Surface\NutsAndBolts\Color;
use Surface\VelvetGE\VelvetGraphicsEngine;

/*
 * A graphics engine is the way in to one graphics library: it hands out its framebuffer, its
 * rasterizer, its raycaster and its painter, and says which of those it has. Velvet, the CPU
 * engine, hands out Rendering's drivers.
 */

function velvetEngine(bool $raycasting = false, array $config = []): VelvetGraphicsEngine
{
    return new VelvetGraphicsEngine(framebuffers($config), rasterize($config), painters($config), $raycasting ? raycasters($config) : null);
}

function engines(array $config = []): GraphicsManager
{
    return (new GraphicsManager(fakeConfig($config)))->extend('velvet', fn () => velvetEngine());
}

it('makes an engine once, and names velvet as the default unless config names another', function (): void {
    $manager = engines();

    expect($manager->getDefaultEngine())->toBe('velvet')
        ->and($manager->engine())->toBe($manager->engine('velvet'))
        ->and($manager->engines())->toBe(['velvet'])
        ->and(engines(['graphics.default' => 'metal'])->getDefaultEngine())->toBe('metal');
});

it('takes engines a package registers, and lets go of one that is replaced', function (): void {
    $manager = engines();
    $first = $manager->engine('velvet');
    $manager->extend('velvet', fn () => velvetEngine());

    expect($manager->engine('velvet'))->not->toBe($first);

    $other = new class extends GraphicsEngine {
        public function name(): string
        {
            return 'other';
        }

        protected function parts(): array
        {
            return [];
        }

        public function painter(array $args = []): Painter
        {
            throw DrawingException::notOffered($this->name(), Part::PAINTER);
        }
    };
    $manager->extend('other', fn (GraphicsManager $given) => $given === $manager ? $other : null);

    expect($manager->engine('other'))->toBe($other)
        ->and($manager->engines())->toBe(['velvet', 'other']);
});

it('refuses an engine nobody registered, naming the package a GPU engine comes with', function (string $name, string $says): void {
    expect(fn () => engines()->engine($name))->toThrow(DrawingException::class, $says);
})->with([
    ['nothing', "No graphics engine named 'nothing' is registered (registered: velvet)."],
    ['metal', 'It comes with jovian/venusian-metal.'],
    ['vulkan', 'It comes with jovian/venusian-vulkan.'],
]);

it('says which parts it has, and refuses a part it does not have by name', function (): void {
    $bare = new class extends GraphicsEngine {
        public function name(): string
        {
            return 'bare';
        }

        protected function parts(): array
        {
            return [Part::PAINTER];
        }

        public function painter(array $args = []): Painter
        {
            throw new DrawingException('not in this test');
        }
    };
    $clip = new Region(0, 0, 4, 4);

    expect($bare->offers(Part::PAINTER))->toBeTrue()
        ->and($bare->offers(Part::RAYCASTER))->toBeFalse()
        ->and(fn () => $bare->framebuffer(4, 4))->toThrow(DrawingException::class, 'The bare engine has no framebuffer.')
        ->and(fn () => $bare->rasterizer($clip))->toThrow(DrawingException::class, 'The bare engine has no rasterizer.')
        ->and(fn () => $bare->raycaster(Grid::of([[1]])))->toThrow(DrawingException::class, 'The bare engine has no raycaster.');
});

it('offers a raycaster only when raycasting is on', function (): void {
    expect(velvetEngine()->offers(Part::RAYCASTER))->toBeFalse()
        ->and(fn () => velvetEngine()->raycaster(Grid::of([[1, 1], [1, 0]])))->toThrow(DrawingException::class, 'Turn raycasting on')
        ->and(velvetEngine(raycasting: true)->offers(Part::RAYCASTER))->toBeTrue()
        ->and(velvetEngine(raycasting: true)->raycaster(Grid::of([[1, 1], [1, 0]]))->grid()->width)->toBe(2);
});

it('makes velvet\'s framebuffers in every mode, in the format and from the driver asked for', function (): void {
    $velvet = velvetEngine();

    expect($velvet->framebuffer(8, 4)->hostFormat()->equals(FormatSpec::rgba8()))->toBeTrue()
        ->and($velvet->framebuffer(8, 4, ['format' => FormatSpec::bgra8()])->hostFormat()->equals(FormatSpec::bgra8()))->toBeTrue()
        ->and($velvet->framebuffer(8, 4, ['mode' => 'ring', 'frames' => 3]))->toBeInstanceOf(RingFramebuffer::class)
        ->and($velvet->framebuffer(8, 4, ['mode' => 'paged', 'page_rows' => 2]))->toBeInstanceOf(PagedFramebuffer::class)
        ->and($velvet->framebuffer(8, 4, ['framebuffers' => 'native'])->memory())->toBeNull();
});

it('refuses a framebuffer it cannot make from its arguments', function (Closure $make, string $says): void {
    expect($make)->toThrow(DrawingException::class, $says);
})->with([
    'an argument it does not take' => [fn () => velvetEngine()->framebuffer(4, 4, ['samples' => 4]), "velvet does not take 'samples'"],
    'no width' => [fn () => velvetEngine()->framebuffer(0, 4), "'width' and 'height' are at least 1."],
    'a mode that is not one' => [fn () => velvetEngine()->framebuffer(4, 4, ['mode' => 'sideways']), "'mode' is one of full, dirty, epaper, paged, ring"],
    'paged with no page rows' => [fn () => velvetEngine()->framebuffer(4, 4, ['mode' => 'paged']), "'paged' needs 'page_rows'."],
    'a format that is not one' => [fn () => velvetEngine()->framebuffer(4, 4, ['format' => 'rgba8']), "'format' is a FormatSpec."],
]);

it('hands out a rasterizer that paints shapes and answers spans', function (): void {
    $velvet = velvetEngine();
    $rasterizer = $velvet->rasterizer(new Region(0, 0, 8, 8));
    $frame = $velvet->framebuffer(8, 8);
    $rasterizer->fillBatch([['path', [[[1, 1], [5, 1], [5, 5], [1, 5]]], Surface\Contracts\Rendering\Rasterization\FillRule::NON_ZERO, 0xFF0000FF, new Region(0, 0, 8, 8)]], $frame);

    expect($rasterizer)->toBeInstanceOf(ShapeRasterizer::class)->toBeInstanceOf(Rasterizer::class)
        ->and($rasterizer->edges())->toBe(Edges::ANTIALIASED)
        ->and($velvet->rasterizer(new Region(0, 0, 8, 8), Edges::HARD)->edges())->toBe(Edges::HARD)
        ->and($rasterizer->fillRect(1, 1, 2, 2))->not->toBe('')
        ->and(bin2hex($frame->flushRegion(new Region(2, 2, 1, 1), FormatSpec::rgba8())))->toBe('ff0000ff');
});

it('hands out a painter over a framebuffer it is given, or one it makes', function (): void {
    $velvet = velvetEngine();
    $frame = $velvet->framebuffer(8, 8);
    $given = $velvet->painter(['framebuffer' => $frame, 'edges' => 'hard']);
    $given->frame(fn (Painter $g) => $g->clear(Color::rgb(0, 0, 255)));
    $made = $velvet->painter(['width' => 6, 'height' => 3, 'mode' => 'dirty']);

    expect($given->framebuffer())->toBe($frame)
        ->and(bin2hex($frame->flushRegion(new Region(0, 0, 1, 1), FormatSpec::rgba8())))->toBe('0000ffff')
        ->and([$made->width(), $made->height()])->toBe([6, 3])
        ->and($velvet->painter(['width' => 4, 'height' => 4, 'painters' => 'native'])->name())->toBe('velvet');
});

it('refuses a painter it cannot build from its arguments', function (array $args, string $says): void {
    expect(fn () => velvetEngine()->painter($args))->toThrow(DrawingException::class, $says);
})->with([
    'nothing to draw into' => [[], "velvet needs a 'framebuffer', or a 'width' and a 'height' to make one."],
    'an argument it does not take' => [['width' => 4, 'height' => 4, 'samples' => 4], "velvet does not take 'samples'"],
    'a framebuffer that is not one' => [['framebuffer' => 'mine'], "'framebuffer' is a Framebuffer."],
    'a framebuffer and a size' => [['framebuffer' => nativeFull(FormatSpec::rgba8(), 4, 4), 'width' => 4], "'framebuffer' comes alone"],
    'an output that is not one' => [['output' => 'canvas'], "'output' is an OutputTarget"],
    'edges that are not one' => [['width' => 4, 'height' => 4, 'edges' => 'soft'], "'edges' is 'hard' or 'antialiased'"],
    'a size that is not whole' => [['width' => 4.5, 'height' => 4], "'width' and 'height' are integers."],
]);

it('is a graphics engine by its contract', function (): void {
    expect(velvetEngine())->toBeInstanceOf(GraphicsEngineContract::class);
});
