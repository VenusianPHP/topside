<?php

declare(strict_types=1);

use Surface\Contracts\Graphics\DrawingException;
use Surface\Contracts\Rendering\Framebuffers\DamageGranularity;
use Surface\Contracts\Rendering\Framebuffers\Region;
use Surface\Contracts\Rendering\Recording\Recorder;
use Surface\Contracts\Typography\FontSource;
use Surface\Contracts\Typography\GlyphRun;
use Surface\Contracts\Typography\TypesetLine;
use Surface\Contracts\Typography\TypesetText;
use Surface\NutsAndBolts\Affine;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\Path;
use Surface\Rendering\Glyphs\GlyphDrawingDriver;
use Surface\Rendering\Glyphs\GlyphLibraryManager;
use Surface\Rendering\Recording\Boxes;
use Surface\Rendering\Recording\DrawCommandQueue;
use Surface\Rendering\Recording\InkBoxes;
use Surface\Rendering\Recording\Queues\CLandQueue;
use Surface\Rendering\Recording\Queues\PHPLandQueue;

// text(): typeset text recorded as one 'glyphs' command, by VelvetGE's recorder over either queue.
// The ink comes from the glyphs' outlines: 'A' is a box from (100, 0) to (400, 700) in a face of
// 1000 units an em, y up; a space has none.

function inkFace(): FakeFace
{
    $box = (new Path())->moveTo(100, 0)->lineTo(400, 0)->lineTo(400, 700)->lineTo(100, 700)->close();

    return new FakeFace(new FontSource('/fonts/ink.ttf'), [65, 32], outlines: [65 => $box]);
}

/** 'A A' at 20 units, its baseline 16 down: each 'A' inks x + 2..8, y 2..16. */
function inkText(FakeFace $face, array $glyphs = [65, 32, 65]): TypesetText
{
    $run = new GlyphRun($face->source, 20.0, $glyphs, [0.0, 10.0, 20.0], [16.0, 16.0, 16.0]);

    return new TypesetText([new TypesetLine(0.0, 16.0, 20.0, 0.0, 30.0, [$run])], 30.0, 20.0);
}

dataset('queues', ['php', 'c']);

/** VelvetGE's recorder over the named queue, its ink read from $face. */
function inkRecorder(string $queue, FakeFace $face): Recorder
{
    return new DrawCommandQueue($queue === 'c' ? new CLandQueue() : new PHPLandQueue(), new InkBoxes(new FakeFaces($face)));
}

function textRecorder(string $queue, FakeFace $face): Recorder
{
    $recorder = inkRecorder($queue, $face);
    $recorder->begin(new Region(0, 0, 200, 100));

    return $recorder;
}

it('records text as one command: its runs, the transform moved to its origin, and its ink\'s box through the transform', function (string $queue): void {
    $face = inkFace();
    $text = inkText($face);
    $recorder = textRecorder($queue, $face);
    $recorder->transform(new Affine(2, 0, 0, 2, 10, 20));
    $recorder->text($text, 3, 4, Color::rgb(255, 0, 0));

    expect($recorder->finish())->toEqual([[
        'glyphs', $text->runs(),
        // the origin (3, 4) through the transform: (16, 28)
        [2.0, 0.0, 0.0, 2.0, 16.0, 28.0],
        0xFF0000FF,
        // ink 2..28 across, 2..16 down, moved by (3, 4): (5, 6) and (31, 20); through the transform (20, 32) and (72, 60); a pixel out
        [19.0, 31.0, 73.0, 61.0],
        new Region(0, 0, 200, 100),
    ]]);
})->with('queues');

it('records nothing for text with no ink, or under an empty clip', function (string $queue): void {
    $face = inkFace();
    $recorder = textRecorder($queue, $face);
    $recorder->text(inkText($face, [32, 32, 32]), 0, 0, Color::rgb(0, 0, 0));
    $recorder->clip(new Region(150, 0, 0, 0));
    $recorder->text(inkText($face), 0, 0, Color::rgb(0, 0, 0));

    expect($recorder->finish())->toBe([]);
})->with('queues');

it('reads each glyph\'s outline once, however often the text or the glyph is drawn', function (string $queue): void {
    $face = inkFace();
    $recorder = textRecorder($queue, $face);
    $recorder->text(inkText($face), 0, 0, Color::rgb(0, 0, 0));
    $recorder->text(inkText($face), 0, 30, Color::rgb(0, 0, 0));
    $recorder->finish();

    expect($face->asked)->toBe([65 => 1, 32 => 1]);
})->with('queues');

it('redraws nothing for the same text in the same place, and the old and new place for moved text', function (string $queue): void {
    $face = inkFace();
    $text = inkText($face);
    $recorder = textRecorder($queue, $face);
    $grain = new DamageGranularity(1, 1, 200, 100);
    $surface = new Region(0, 0, 200, 100);
    $recorder->text($text, 0, 0, Color::rgb(0, 0, 0));
    $recorder->finish();

    $recorder->begin($surface);
    $recorder->text($text, 0, 0, Color::rgb(0, 0, 0));
    $recorder->finish();
    expect($recorder->changed($surface, $grain))->toBe([]);

    $recorder->begin($surface);
    $recorder->text($text, 100, 0, Color::rgb(0, 0, 0));
    $recorder->finish();
    // reach [1, 1, 29, 17] and [101, 1, 129, 17]
    expect($recorder->changed($surface, $grain))->toEqual([new Region(1, 1, 28, 16), new Region(101, 1, 28, 16)]);
})->with('queues');

it('refuses text as it refuses a shape: outside a frame, a number not finite, a point past the limit', function (string $queue): void {
    $face = inkFace();
    $closed = inkRecorder($queue, $face);
    expect(fn () => $closed->text(inkText($face), 0, 0, Color::rgb(0, 0, 0)))->toThrow(DrawingException::outsideFrame('text'));

    $recorder = textRecorder($queue, $face);
    expect(fn () => $recorder->text(inkText($face), NAN, 0, Color::rgb(0, 0, 0)))->toThrow(DrawingException::notFinite('x'));
    $recorder->scale(1000);
    expect(fn () => $recorder->text(inkText($face), 0, 2.0e4, Color::rgb(0, 0, 0)))->toThrow(DrawingException::pastLimit('y', 2.0e7));
})->with('queues');

it('boxes a glyphs command by its reach, inside its clip', function (): void {
    $command = ['glyphs', [], [1.0, 0.0, 0.0, 1.0, 0.0, 0.0], 0, [9.5, -3.0, 16.5, 14.0], new Region(0, 0, 15, 50)];

    expect(Boxes::box($command, new Region(0, 0, 100, 100)))->toEqual(new Region(9, 0, 6, 14));
});

it('hands out the glyph driver rendering.glyphs.default names', function (): void {
    $manager = new class extends GlyphLibraryManager {
        public function __construct()
        {
            $this->config = fakeConfig(['rendering.glyphs.default' => 'library']);
        }

        protected function callCustomCreator(string $driver): mixed
        {
            return $this->customCreators[$driver]();
        }
    };
    $library = new class extends GlyphDrawingDriver {
        public function driver(): string { return 'library'; }

        public function glyphs(\Surface\Contracts\Rendering\Rasterization\Edges $edges): \Surface\Contracts\Rendering\Glyphs\GlyphPainter
        {
            throw new LogicException('not drawn here');
        }
    };
    $manager->extend('library', fn () => $library);

    expect($manager->driver())->toBe($library);
});
