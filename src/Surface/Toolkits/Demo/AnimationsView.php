<?php

namespace Surface\Toolkits\Demo;

use Surface\Contracts\Graphics\ActorStage;
use Surface\Contracts\Graphics\Resource;
use Surface\Contracts\Toolkits\Composition;
use Surface\Contracts\Toolkits\ViewBuilder;
use Surface\Graphics\GEPainter;
use Surface\Toolkits\Demo\Animations\Animation;
use Surface\Toolkits\Demo\Animations\Bounces;
use Surface\Toolkits\Demo\Animations\Bubbles;
use Surface\Toolkits\Demo\Animations\Particles;
use Surface\Toolkits\Demo\Animations\Paths;
use Surface\Toolkits\Demo\Animations\Rects;
use Surface\Toolkits\Navigation\Navigator;
use Surface\Toolkits\Support\Live;

/**
 * Five of Blend2D's demos, each a class in Demo/Animations: pick one from the tab bar, and its
 * page shows what it is and its own controls, over the canvas. One canvas, one painter and one
 * timer serve all five; only the animation picked is moved and drawn.
 *
 * The frame rate is the page's measure of the engine: "Limit FPS" holds it to 120 a second, as
 * Blend2D's demos do; cleared, a frame is drawn as often as the event loop turns. The display
 * shows no more frames than it refreshes, so the page counts both: frames drawn and frames shown.
 */
class AnimationsView implements Composition
{
    public function name(): string
    {
        return 'animations';
    }

    public function title(): string
    {
        return 'Animations';
    }

    public function compose(ViewBuilder $ui, array $args): void
    {
        $top = $ui->row('top', 12);
        $top->button('back', '‹ Back', [AnimationsController::class, 'back']);
        $top->label('heading', 'Animations', 'title');

        $engine = $ui->engine();
        if (! $engine->supports(Resource::PAINTER)) {
            $ui->label('about', 'The "'.$engine->name().'" engine has no painter, so these animations cannot be drawn with it.', 'body');

            return;
        }

        $modules = $engine->modules();
        $ui->label('engine', 'Drawn by the "'.$engine->name().'" engine on the '.$engine->backend()->value.' backend'
            .($modules === [] ? '.' : ': '.implode('  ·  ', array_map(fn (string $part, string $driver): string => "{$part}: {$driver}", array_keys($modules), $modules))), 'footnote');

        /** @var array<string, Animation> $animations by name */
        $animations = [];
        foreach ([new Rects, new Bubbles, new Particles, new Paths, new Bounces] as $animation) {
            $animations[strtolower($animation->title())] = $animation;
        }
        $current = 'rects';

        /** @var GEPainter|null $painter on the framebuffer attached at the canvas's current size */
        $painter = null;
        $scale = 1.0;
        $playing = true;
        $limit = true;                      // 120 frames a second, as Blend2D's "Limit FPS"
        [$drawn, $since, $last] = [0, microtime(true), microtime(true)];
        [$rate, $showing, $shownAt, $spent] = [0.0, 0.0, 0, 0.0];   // $spent: seconds drawing, over the last second
        $counted = null;                   // the framebuffer $shownAt was read from

        // a tab bar picks the animation: each tab's page holds what it is and its own controls, and
        // the one canvas under the tabs draws whichever is picked
        $names = array_keys($animations);
        $ui->tabs('demos', array_map(fn (Animation $animation): string => $animation->title(), array_values($animations)),
            function (ViewBuilder $page, int $i) use ($animations, $names): void {
                $animation = $animations[$names[$i]];
                $page->label('about', $animation->about(), 'body');
                $animation->controls($page, fn () => app(Live::class)->refresh());
            },
            function (int $i) use ($names, &$current, &$painter): void {
                $current = $names[$i];
                $painter?->invalidate();
                app(Live::class)->refresh();
            });

        $draw = function () use (&$painter, &$scale, &$current, &$drawn, &$spent, $animations): void {
            if (is_null($painter)) {
                return;
            }
            $start = hrtime(true);
            $animation = $animations[$current];
            $screen = $painter->framebuffer();
            $animation->resize($screen->width() / $scale, $screen->height() / $scale);

            $painter->frame(function (GEPainter $g) use ($animation, $scale): void {
                $g->scale($scale);
                $animation->draw($g);
            });
            $screen->present();
            $spent += (hrtime(true) - $start) / 1e9;
            $drawn++;
        };

        $ui->canvas('scene', 420, function (ActorStage $stage) use (&$painter, &$scale, $draw, $engine): void {
            $painter = $engine->painter($engine->attach($stage));
            $scale = $stage->pixelScale();
            $draw();
        });

        $tick = function () use ($draw, &$current, &$playing, &$last, &$drawn, &$since, &$rate, &$showing, &$shownAt, &$counted, &$spent, &$painter, $animations): void {
            if (app(Navigator::class)->current() !== '/animations') {
                return;
            }
            // by the clock: 120 of Blend2D's ticks a second, however often a frame is drawn. A long
            // pause (a resize, another page) is not caught up on.
            $now = microtime(true);
            $playing && $animations[$current]->step(min($now - $last, 0.1) * 120.0);
            $last = $now;
            $draw();

            $elapsed = $now - $since;
            if ($elapsed >= 1.0) {
                // a canvas resized has a new framebuffer, counting from 0: its frames count from there
                $screen = $painter?->framebuffer();
                $screen === $counted || $shownAt = 0;
                $counted = $screen;
                $shown = $screen?->shown() ?? 0;
                [$rate, $showing] = [$drawn / $elapsed, max(0, $shown - $shownAt) / $elapsed];
                $spent = $drawn > 0 ? $spent / $drawn : 0.0;
                app(Live::class)->refresh();
                [$drawn, $since, $shownAt] = [0, $now, $shown];
                $spent = 0.0;
            }
        };
        // every() under a name replaces the timer of that name; one asked for 100,000 times a second
        // fires once a turn of the loop
        $run = function () use ($tick, &$limit, &$last): void {
            $last = microtime(true);
            app('event-loop')->every($limit ? 1 / 120 : 1 / 100000, $tick, 'animations.frame');
        };

        $status = $ui->row('status', 12);
        $status->button('pause', 'Pause / Play', function () use (&$playing): void {
            $playing = ! $playing;
        });
        $status->checkbox('limit', 'Limit FPS', $limit, function (bool $on) use (&$limit, $run): void {
            $limit = $on;
            $run();
        });
        $status->label('fps', function () use (&$rate, &$showing, &$spent, &$current, $animations): string {
            return $rate === 0.0
                ? 'Measuring…'
                : sprintf('%.0f drawn, %.0f shown a second  ·  %.2f ms a frame  ·  %d drawn', $rate, $showing, $spent * 1000, $animations[$current]->count());
        });

        $run();
    }
}
