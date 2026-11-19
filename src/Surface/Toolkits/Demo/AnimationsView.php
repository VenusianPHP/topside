<?php

namespace Surface\Toolkits\Demo;

use Surface\Contracts\Graphics\ActorStage;
use Surface\Contracts\Graphics\Canvas;
use Surface\Contracts\Graphics\DrawingSurface;
use Surface\Contracts\Toolkits\Composition;
use Surface\Contracts\Toolkits\ViewBuilder;
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
 * page shows what it is and its own controls, over the canvas. One canvas, one surface and one
 * timer serve all five; only the animation picked is moved and drawn.
 *
 * The frame rate is the page's measure of the engine: "Limit FPS" holds it to 120 a second, as
 * Blend2D's demos do; cleared, a frame is drawn as often as the event loop turns.
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
        $ui->label('engine', 'Drawn by the "'.$engine->name().'" engine on the '.($engine->gpu() ? 'GPU' : 'CPU').'.', 'footnote');

        /** @var array<string, Animation> $animations by name */
        $animations = [];
        foreach ([new Rects, new Bubbles, new Particles, new Paths, new Bounces] as $animation) {
            $animations[strtolower($animation->title())] = $animation;
        }
        $current = 'rects';

        /** @var DrawingSurface|null $surface on the canvas, at its current size */
        $surface = null;
        $scale = 1.0;
        $playing = true;
        $limit = true;                      // 120 frames a second, as Blend2D's "Limit FPS"
        [$drawn, $since, $last] = [0, microtime(true), microtime(true)];
        [$rate, $spent] = [0.0, 0.0];      // $spent: seconds drawing, over the last second

        // a tab bar picks the animation: each tab's page holds what it is and its own controls, and
        // the one canvas under the tabs draws whichever is picked
        $names = array_keys($animations);
        $ui->tabs('demos', array_map(fn (Animation $animation): string => $animation->title(), array_values($animations)),
            function (ViewBuilder $page, int $i) use ($animations, $names): void {
                $animation = $animations[$names[$i]];
                $page->label('about', $animation->about(), 'body');
                $animation->controls($page, fn () => app(Live::class)->refresh());
            },
            function (int $i) use ($names, &$current): void {
                $current = $names[$i];
                app(Live::class)->refresh();
            });

        $draw = function () use (&$surface, &$scale, &$current, &$drawn, &$spent, $animations): void {
            if (is_null($surface)) {
                return;
            }
            $start = hrtime(true);
            $animation = $animations[$current];
            [$width, $height] = $surface->size();
            $animation->resize($width / $scale, $height / $scale);

            $surface->draw(function (Canvas $g) use ($animation, $scale): void {
                $g->scale($scale, $scale);
                $animation->draw($g);
            })->present();
            $spent += (hrtime(true) - $start) / 1e9;
            $drawn++;
        };

        // a surface follows its stage's size frame by frame: one is made per stage, kept while the stage is
        $shown = null;
        $ui->canvas('scene', 420, function (ActorStage $stage) use (&$surface, &$scale, &$shown, $draw, $engine): void {
            if ($shown !== $stage) {
                $surface = $engine->surface($stage);
                $shown = $stage;
            }
            $scale = $stage->pixelScale();
            $draw();
        });

        $tick = function () use ($draw, &$current, &$playing, &$last, &$drawn, &$since, &$rate, &$spent, $animations): void {
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
                $rate = $drawn / $elapsed;
                $spent = $drawn > 0 ? $spent / $drawn : 0.0;
                app(Live::class)->refresh();
                [$drawn, $since] = [0, $now];
                $spent = 0.0;
            }
        };
        // a frame clock under a name replaces the one of that name; unlimited, a frame every turn of the loop
        $run = function () use ($tick, &$limit, &$last): void {
            $last = microtime(true);
            FrameClock::run('animations.frame', $limit ? 120 : 0, $tick);
        };

        $status = $ui->row('status', 12);
        $status->button('pause', 'Pause / Play', function () use (&$playing): void {
            $playing = ! $playing;
        });
        $status->checkbox('limit', 'Limit FPS', $limit, function (bool $on) use (&$limit, $run): void {
            $limit = $on;
            $run();
        });
        $status->label('fps', function () use (&$rate, &$spent, &$current, $animations): string {
            return $rate === 0.0
                ? 'Measuring…'
                : sprintf('%.0f frames a second  ·  %.2f ms a frame  ·  %d drawn', $rate, $spent * 1000, $animations[$current]->count());
        });

        $run();
    }
}
