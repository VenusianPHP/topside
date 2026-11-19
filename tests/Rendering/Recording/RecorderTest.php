<?php

declare(strict_types=1);

use Surface\Contracts\Rendering\Enums\FillRule;
use Surface\Contracts\Rendering\Recording\Enums\CommandReach;
use Surface\Contracts\Rendering\Recording\Enums\RecordingError;
use Surface\Contracts\Rendering\Recording\Exceptions\CommandRecorderException;
use Surface\Contracts\Rendering\Recording\Recorder;
use Surface\Rendering\CommandRecorders\CCommandRecorder;
use Surface\Rendering\CommandRecorders\PHPCommandRecorder;
use Surface\Rendering\Region;

/*
 * A recorder's commands are what a drawer draws, and its changed() is what gets redrawn. Each test states the
 * command or the rects by hand, from the arithmetic the Recorder contract documents, and holds both drivers to it.
 * The 'c' driver needs ext-comrec: without it these tests fail, they do not skip.
 */

dataset('recorders', [
    'php' => [fn (): Recorder => new PHPCommandRecorder()],
    'c' => [fn (): Recorder => new CCommandRecorder()],
]);

const RED = 0xFF0000FF;
const WHITE = 0xFFFFFFFF;

/** One frame, 200 x 100, drawn by $draw; its commands. */
function recorded(Recorder $g, Closure $draw, mixed $clip = 'whole'): array
{
    $g->begin(200, 100, $clip);
    $draw($g);

    return $g->finish();
}

it('records each shape as its command, points in frame pixels', function (Closure $make, Closure $draw, array $commands): void {
    expect(recorded($make(), $draw))->toEqual($commands);
})->with('recorders')->with([
    'clear' => [fn ($g) => $g->clear(RED), [['clear', RED]]],
    'fillRect' => [fn ($g) => $g->fillRect(10, 10, 60, 40, RED), [['path', [[[10, 10], [70, 10], [70, 50], [10, 50]]], FillRule::NON_ZERO, RED, 'whole']]],
    'fillTriangle' => [fn ($g) => $g->fillTriangle(0, 0, 10, 0, 5, 8, RED), [['path', [[[0, 0], [10, 0], [5, 8]]], FillRule::NON_ZERO, RED, 'whole']]],
    'strokeRect' => [fn ($g) => $g->strokeRect(10, 10, 60, 40, RED, 2.0), [['polyline', [[10, 10], [70, 10], [70, 50], [10, 50]], 2.0, true, RED, 'whole']]],
    'line' => [fn ($g) => $g->line(0, 0, 50, 50, RED, 3.0), [['polyline', [[0, 0], [50, 50]], 3.0, false, RED, 'whole']]],
    'fillEllipse' => [fn ($g) => $g->fillEllipse(100, 50, 20, 10, RED), [['ellipse', 100, 50, 20, 10, RED, 'whole']]],
    'strokeEllipse' => [fn ($g) => $g->strokeEllipse(100, 50, 20, 10, RED, 2.0), [['ring', 100, 50, 20, 10, 2.0, RED, 'whole']]],
    'roundRect, no border' => [fn ($g) => $g->roundRect(20, 20, 80, 40, [4, 4, 4, 4], RED, null, 0.0), [['roundrect', 20, 20, 100, 60, [4, 4, 4, 4], RED, 'whole']]],
    'roundRect, border 2: the fill inset by 2, then the ring' => [fn ($g) => $g->roundRect(20, 20, 80, 40, [4, 4, 4, 4], RED, WHITE, 2.0), [
        ['roundrect', 22, 22, 98, 58, [2, 2, 2, 2], RED, 'whole'],
        ['roundring', 20, 20, 100, 60, [4, 4, 4, 4], 2.0, WHITE, 'whole'],
    ]],
    'shadow, moved by its offset' => [fn ($g) => $g->shadow(20, 20, 80, 40, 4.0, 6.0, RED, 2.0, 3.0), [['shadow', 22, 23, 102, 63, 4.0, 6.0, RED, 'whole']]],
]);

it('moves a shape through the transform when it records it', function (Closure $make): void {
    $commands = recorded($make(), function (Recorder $g): void {
        $g->concat(1, 0, 0, 1, 20, 10);
        $g->fillRect(0, 0, 60, 40, RED);
    });

    expect($commands)->toEqual([['path', [[[20, 10], [80, 10], [80, 50], [20, 50]]], FillRule::NON_ZERO, RED, 'whole']]);
})->with('recorders');

it('scales an ellipse that keeps its axes, and its stroke by the transform', function (Closure $make): void {
    $commands = recorded($make(), function (Recorder $g): void {
        $g->concat(2, 0, 0, 3, 5, 7);
        $g->fillEllipse(10, 10, 4, 2, RED);        // center (2*10+5, 3*10+7), radii (4*2, 2*3)
        $g->line(0, 0, 10, 0, RED, 1.0);           // stroke 1 * sqrt(2 * 3)
    });

    expect($commands[0])->toEqual(['ellipse', 25, 37, 8, 6, RED, 'whole'])
        ->and($commands[1][1])->toEqual([[5, 7], [25, 7]])
        ->and($commands[1][2])->toEqualWithDelta(sqrt(6), 1e-12);
})->with('recorders');

it('records a turned ellipse as a path of straight sides', function (Closure $make): void {
    $commands = recorded($make(), function (Recorder $g): void {
        $g->concat(0, 1, -1, 0, 100, 0);           // a quarter turn
        $g->fillEllipse(0, 0, 20, 10, RED);
    });

    // sides = ceil(pi * sqrt(20 / 0.2) * 1.01) = 32
    expect($commands)->toHaveCount(1)
        ->and($commands[0][0])->toBe('path')
        ->and($commands[0][1][0])->toHaveCount(32)
        ->and($commands[0][1][0][0])->toEqualWithDelta([100, 20], 1e-9);
})->with('recorders');

it('gives every command the clip value it was recorded under, as the object it was given', function (Closure $make): void {
    $whole = new Region(0, 0, 200, 100);
    $inner = new Region(120, 20, 40, 40);

    $commands = recorded($make(), function (Recorder $g) use ($inner): void {
        $g->fillRect(0, 0, 10, 10, RED);
        $g->push();
        $g->clip($inner, $inner->x, $inner->y, $inner->width, $inner->height);
        $g->fillRect(0, 0, 10, 10, RED);
        $g->pop();
        $g->fillRect(0, 0, 10, 10, RED);
    }, $whole);

    expect($commands[0][4])->toBe($whole)
        ->and($commands[1][4])->toBe($inner)
        ->and($commands[2][4])->toBe($whole);
})->with('recorders');

it('brings the transform back on pop()', function (Closure $make): void {
    $g = $make();
    $g->begin(200, 100, 'whole');
    $g->push();
    $g->concat(2, 0, 0, 2, 30, 40);
    expect($g->matrix())->toEqual([2, 0, 0, 2, 30, 40])->and($g->depth())->toBe(1);
    $g->pop();

    expect($g->matrix())->toEqual([1, 0, 0, 1, 0, 0])->and($g->depth())->toBe(0);
})->with('recorders');

it('records nothing for a shape with no size, or under an empty clip', function (Closure $make): void {
    $commands = recorded($make(), function (Recorder $g): void {
        $g->fillRect(10, 10, 0, 40, RED);
        $g->line(0, 0, 5, 5, RED, 0.0);
        $g->clip('empty', 10, 10, 0, 10);
        $g->fillRect(10, 10, 60, 40, RED);
    });

    expect($commands)->toBe([]);
})->with('recorders');

it('keeps a layer\'s commands in a list of their own', function (Closure $make): void {
    $g = $make();
    $g->begin(200, 100, 'whole');
    $g->fillRect(0, 0, 10, 10, RED);
    $g->enter('layer', 0, 0, 50, 50);
    $g->fillRect(5, 5, 10, 10, WHITE);
    $layer = $g->leave();
    $frame = $g->finish();

    expect($layer)->toEqual([['path', [[[5, 5], [15, 5], [15, 15], [5, 15]]], FillRule::NON_ZERO, WHITE, 'layer']])
        ->and($frame)->toEqual([['path', [[[0, 0], [10, 0], [10, 10], [0, 10]]], FillRule::NON_ZERO, RED, 'whole']]);
})->with('recorders');

it('records a command made elsewhere as it is', function (Closure $make): void {
    $mine = ['mine', [[1, 2], [30, 40]], 'anything'];

    $commands = recorded($make(), fn (Recorder $g) => $g->append($mine, CommandReach::POINTS, 1.0));

    expect($commands)->toBe([$mine]);
})->with('recorders');

it('says where the last frame draws: each command one pixel out, merged', function (Closure $make): void {
    $g = $make();
    recorded($g, function (Recorder $g): void {
        $g->fillRect(10, 10, 60, 40, RED);                                        // 9..71, 9..51
        $g->append(['mine', [[100.5, 20.5], [130, 40]]], CommandReach::POINTS, 1.0);   // 99.5..131, 19.5..41 -> 99..131, 19..41
    });

    expect($g->drawn(200, 100, 1, 1, 200, 100))->toBe([9, 9, 62, 42, 99, 19, 32, 22]);
})->with('recorders');

it('says a clear draws the whole surface', function (Closure $make): void {
    $g = $make();
    recorded($g, fn (Recorder $g) => $g->clear(RED));

    expect($g->drawn(200, 100, 1, 1, 200, 100))->toBe([0, 0, 200, 100]);
})->with('recorders');

it('says nothing changed between two frames that record the same', function (Closure $make): void {
    $g = $make();
    $draw = function (Recorder $g): void {
        $g->clear(RED);
        $g->fillRect(10, 10, 60, 40, WHITE);
        $g->roundRect(100, 10, 40, 40, [4, 4, 4, 4], RED, WHITE, 2.0);
    };
    recorded($g, $draw, new Region(0, 0, 200, 100));
    recorded($g, $draw, new Region(0, 0, 200, 100));       // a new, equal clip object

    expect($g->changed(200, 100, 1, 1, 200, 100))->toBe([]);
})->with('recorders');

it('says where a moved shape was and where it is', function (Closure $make): void {
    $g = $make();
    recorded($g, function (Recorder $g): void {
        $g->clear(RED);
        $g->fillRect(10, 10, 60, 40, WHITE);
    });
    recorded($g, function (Recorder $g): void {
        $g->clear(RED);
        $g->fillRect(80, 10, 60, 40, WHITE);
    });

    // was 9..71, is 79..141; both 9..51 down
    expect($g->changed(200, 100, 1, 1, 200, 100))->toBe([9, 9, 62, 42, 79, 9, 62, 42]);
})->with('recorders');

it('says a changed color changed where the shape is', function (Closure $make): void {
    $g = $make();
    recorded($g, fn (Recorder $g) => $g->fillRect(10, 10, 60, 40, WHITE));
    recorded($g, fn (Recorder $g) => $g->fillRect(10, 10, 60, 40, RED));

    expect($g->changed(200, 100, 1, 1, 200, 100))->toBe([9, 9, 62, 42]);
})->with('recorders');

it('says the first frame changed everywhere it draws', function (Closure $make): void {
    $g = $make();
    recorded($g, fn (Recorder $g) => $g->fillRect(10, 10, 60, 40, WHITE));

    expect($g->changed(200, 100, 1, 1, 200, 100))->toBe([9, 9, 62, 42]);
})->with('recorders');

it('refuses a shape by name, and records nothing of it', function (Closure $make, Closure $draw, RecordingError $error, string $what): void {
    $g = $make();
    $g->begin(200, 100, 'whole');

    try {
        $draw($g);
        $refused = null;
    } catch (CommandRecorderException $e) {
        $refused = $e;
    }

    expect($refused)->not->toBeNull()
        ->and($refused->error)->toBe($error)
        ->and($refused->what)->toBe($what)
        ->and($refused->getCode())->toBe($error->value)
        ->and($g->finish())->toBe([]);
})->with('recorders')->with([
    'a number that is not finite' => [fn ($g) => $g->fillRect(NAN, 0, 10, 10, RED), RecordingError::NOT_FINITE, 'x'],
    'a point past the limit' => [fn ($g) => $g->fillRect(1e9, 0, 10, 10, RED), RecordingError::PAST_LIMIT, 'x'],
    'radii that are not four numbers' => [fn ($g) => $g->roundRect(0, 0, 10, 10, [1, 2], RED, null, 0.0), RecordingError::BAD_RADII, ''],
    'a shadow under an uneven transform' => [function ($g) {
        $g->concat(2, 0, 0, 3, 0, 0);
        $g->shadow(0, 0, 10, 10, 2.0, 4.0, RED, 0.0, 0.0);
    }, RecordingError::UNEVEN, 'shadow'],
    'a transform that is not finite' => [fn ($g) => $g->concat(INF, 0, 0, 1, 0, 0), RecordingError::BAD_MATRIX, ''],
]);

it('refuses a shape when no frame is open', function (Closure $make): void {
    $g = $make();

    try {
        $g->fillRect(0, 0, 10, 10, RED);
        $refused = null;
    } catch (CommandRecorderException $e) {
        $refused = $e;
    }

    expect($g->recording())->toBeFalse()
        ->and($refused?->error)->toBe(RecordingError::NOT_RECORDING)
        ->and($refused?->what)->toBe('fillRect');
})->with('recorders');

it('drops an abandoned frame and keeps the frames before it', function (Closure $make): void {
    $g = $make();
    recorded($g, fn (Recorder $g) => $g->fillRect(10, 10, 60, 40, WHITE));
    $g->begin(200, 100, 'whole');
    $g->fillRect(0, 0, 200, 100, RED);
    $g->abandon();

    expect($g->recording())->toBeFalse()
        ->and($g->drawn(200, 100, 1, 1, 200, 100))->toBe([9, 9, 62, 42]);
})->with('recorders');
