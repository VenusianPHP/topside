<?php

namespace Surface\Toolkits\Demo;

use Surface\Contracts\Graphics\ActorStage;
use Surface\Contracts\Graphics\Canvas;
use Surface\Contracts\Graphics\DrawingSurface;
use Surface\Contracts\Graphics\deprecated2\CastsRays;
use Surface\Contracts\Graphics\deprecated2\Feature;
use Surface\Contracts\Rendering\deprecated\Raycasting\Camera;
use Surface\Contracts\Rendering\deprecated\Raycasting\Grid;
use Surface\Contracts\Rendering\deprecated\Raycasting\Scenery;
use Surface\Contracts\Rendering\Enums\FillRule;
use Surface\Contracts\Toolkits\Composition;
use Surface\Contracts\Toolkits\ViewBuilder;
use Surface\NutsAndBolts\Color;
use Surface\NutsAndBolts\Path;
use Surface\NutsAndBolts\RadialGradient;
use Surface\Toolkits\Navigation\Navigator;
use Surface\Toolkits\Support\Live;

/**
 * One of everything the ViewBuilder makes, working. Read it next to the window.
 *
 * A view is a Composition: a name, a title, and compose(), which describes the page to a
 * ViewBuilder ($ui). The view never names a toolkit. The same compose() draws in AppKit, GTK,
 * Qt and Raven, because each $ui call becomes that toolkit's own widget.
 *
 * Three ideas carry the whole page:
 *
 *   Names.     Every widget has a name, unique in its container. A container call (row, column,
 *              card, group, scroll) gives back a builder for what goes inside it, so the page is
 *              a tree and each widget has a path: "kitchen-sink.content.top.back".
 *
 *   Handlers.  What a widget does is its last argument, in one of three forms:
 *                a route name       '/'                                    goes to that route
 *                a controller call  [KitchenSinkController::class, 'back'] calls that method
 *                a Closure          fn () => ...                           runs here
 *              The controller method and the Closure are given the mail the widget posted.
 *
 *   Live text. A label whose text is a Closure reads it again on each Live::refresh() and shows
 *              the new text. So a handler changes the state and calls refresh(): nothing is
 *              rebuilt, and the handler does not need to know which labels show that state.
 *
 *   Graphics.  A canvas is a stage: a region a graphics engine's frames are shown in. The page
 *              asks Gfx for an engine, attaches it to the stage, draws a frame and presents it.
 *
 * compose() runs one time. After that the page is shown and hidden, never built again.
 */
class KitchenSinkView implements Composition
{
    public function name(): string
    {
        return 'kitchen-sink';
    }

    public function title(): string
    {
        return 'Kitchen sink';
    }

    public function compose(ViewBuilder $ui, array $args): void
    {
        $state = new KitchenSinkState();
        $refresh = fn () => app(Live::class)->refresh();

        $this->top($ui);

        // Everything below scrolls, so the page fits a small window.
        $page = $ui->scroll('page');

        $this->graphics($page);
        $this->text($page);
        $this->buttons($page, $state, $refresh);
        $this->input($page, $state, $refresh);
        $this->rows($page);
        $this->cards($page);
        $this->search($page, $state, $refresh);
        $this->tabs($page, $state);
        $this->reveal($page, $state, $refresh);
        $this->overlay($ui, $page, $state);

        $page->label('later', 'Not on this page: image, which needs an image file.', 'footnote');
    }

    /** A row: its children go left to right. A spacer takes the spare width, so the help button sits at the right edge. */
    private function top(ViewBuilder $ui): void
    {
        $top = $ui->row('top', 12);

        // A controller call as the handler: KitchenSinkController::back() asks the Navigator for the route before this one.
        $top->button('back', '‹ Back', [KitchenSinkController::class, 'back']);
        $top->label('heading', 'Kitchen sink', 'title');
        $top->spacer('gap');
        $top->helpButton('help', fn () => dump('Read KitchenSinkView.php next to this window: each section is one method.'));
    }

    /** label(): the role says what the text is, and the builder decides how that looks. */
    private function text(ViewBuilder $page): void
    {
        $card = $page->card('text', 'Text');

        $card->label('title', 'A title', 'title');
        $card->label('body', 'Body text wraps to the width it is given. Use it for a paragraph, a description, or anything longer than a few words.', 'body');
        $card->label('plain', 'A label with no role is plain text.');
        $card->label('footnote', 'A footnote is small and grey.', 'footnote');
    }

    /** button(): the three handler forms, and a label that follows the state. */
    private function buttons(ViewBuilder $page, KitchenSinkState $state, \Closure $refresh): void
    {
        $card = $page->card('buttons', 'Buttons');

        // Live text: read now, and again on each refresh.
        $card->label('count', fn () => match ($state->clicks) {
            0 => 'Not clicked yet.',
            1 => 'Clicked 1 time.',
            default => "Clicked {$state->clicks} times.",
        });

        $row = $card->row('actions', 8);

        // A Closure as the handler: change the state, then refresh. The label above shows the new count.
        $row->button('add', 'Click me', function () use ($state, $refresh): void {
            $state->clicks++;
            $refresh();
        });

        // $destructive draws the button in the danger colour.
        $row->button('reset', 'Reset', function () use ($state, $refresh): void {
            $state->clicks = 0;
            $refresh();
        }, destructive: true);

        // A controller call as the handler: it prints the click's mail to the terminal.
        $row->button('inspect', 'Print this click', [KitchenSinkController::class, 'inspect']);
    }

    /** input(): Return runs the handler. The Field it gives back reads and empties what was typed. */
    private function input(ViewBuilder $page, KitchenSinkState $state, \Closure $refresh): void
    {
        $card = $page->card('input', 'Input');

        $card->label('greeting', fn () => $state->name === '' ? 'Type a name and press Return.' : "Hello, {$state->name}.");

        $name = $card->input('name', 'Your name', onSubmit: function () use (&$name, $state, $refresh): void {
            $state->name = trim($name->value());
            $refresh();
        });

        // $secret hides what is typed.
        $card->input('secret', 'A secret: the characters are hidden', secret: true);

        $card->button('clear', 'Clear the name', function () use (&$name, $state, $refresh): void {
            $name->clear();
            $state->name = '';
            $refresh();
        });
    }

    /** group(): a titled panel of rows. detail() shows a value; option() is a row the user clicks. */
    private function rows(ViewBuilder $page): void
    {
        $group = $page->group('rows', 'Rows');

        $group->detail('toolkit', 'Toolkit', (string) config('main.presentation.toolkit.driver', 'the default'));
        $group->detail('php', 'PHP', PHP_VERSION);
        $group->detail('route', 'This route', (string) app(Navigator::class)->current());

        // A route name as the handler: the click goes to "/".
        $group->option('home', 'Go to the welcome page', onClick: '/');
        $group->option('print', 'Print to the terminal', onClick: fn () => dump('An option row was clicked.'));
    }

    /** card() and link(): a card is a titled panel; cards in a row share its width. A link opens in the browser. */
    private function cards(ViewBuilder $page): void
    {
        $row = $page->row('cards', 16);

        $one = $row->card('one', 'A card');
        $one->label('body', 'A card holds anything a column holds.', 'body');

        $two = $row->card('two', 'A card with a link');
        $two->label('body', 'Guides and the package reference:', 'body');
        $two->link('site', 'venusian.projectsaturnstudios.com', 'https://venusian.projectsaturnstudios.com');
    }

    /** search(): a field whose list fills as the user types. A pick from the list becomes the value. */
    private function search(ViewBuilder $page, KitchenSinkState $state, \Closure $refresh): void
    {
        $card = $page->card('search', 'Search');

        $card->label('picked', fn () => $state->fruit === '' ? 'Type a fruit, pick one, press Return.' : "You chose {$state->fruit}.");

        // $matches is given what was typed and answers the list to show under the field.
        $fruits = ['Apple', 'Apricot', 'Banana', 'Blackberry', 'Cherry', 'Grape', 'Lemon', 'Mango', 'Orange', 'Peach', 'Pear', 'Plum'];
        $matches = fn (string $query): array => array_values(array_filter($fruits, fn (string $fruit): bool => stripos($fruit, $query) === 0));

        // Like input(), it gives back a Field, and Return runs $onSubmit.
        $field = $card->search('fruit', '', 'A fruit', $matches, function () use (&$field, $state, $refresh): void {
            $state->fruit = trim($field->value());
            $refresh();
        });
    }

    /** tabs(): a toolbar over one page per item. It gives back a Closure that shows a page, as a click on its item does. */
    private function tabs(ViewBuilder $page, KitchenSinkState $state): void
    {
        $card = $page->card('tabs', 'Tabs');

        // A Closure item is a label read again on each refresh, like a live label.
        $items = ['First', 'Second', fn () => "Clicks: {$state->clicks}"];

        // $page builds each page into its own builder; $i is the page's index.
        $show = $card->tabs('pages', $items, function (ViewBuilder $tab, int $i): void {
            $tab->label('text', match ($i) {
                0 => 'The first page. Click an item above to change pages.',
                1 => 'The second page. Pages cross-fade when they change.',
                default => "The third item's label counts the Buttons card's clicks.",
            }, 'body');
        });

        // The returned Closure, from a handler: it moves the toolbar too.
        $card->button('last', 'Show the last page', fn () => $show(count($items) - 1));
    }

    /** reveal(): a section that opens and closes smoothly. Its Closure is read now and on each refresh. */
    private function reveal(ViewBuilder $page, KitchenSinkState $state, \Closure $refresh): void
    {
        $card = $page->card('reveal', 'Reveal');

        $card->button('toggle', 'Open or close', function () use ($state, $refresh): void {
            $state->open = ! $state->open;
            $refresh();
        });

        // Closed at first. A refresh that changes what the Closure answers opens or closes it.
        $more = $card->reveal('more', fn () => $state->open);
        $more->label('text', 'This section opens and closes. What is under it moves with its height.', 'body');
        $more->label('note', 'It is built once, closed: opening it shows what is already there.', 'footnote');
    }

    /** overlay(): a pane over the whole view. hide() on its builder closes it. */
    private function overlay(ViewBuilder $ui, ViewBuilder $page, KitchenSinkState $state): void
    {
        $card = $page->card('overlay', 'Overlay');
        $card->label('text', 'An overlay covers the page until it is closed.', 'body');

        // Built when clicked, on the view's own builder ($ui). Names are unique in a container, so each
        // overlay gets its own; a closed overlay is hidden and takes no space.
        $card->button('open', 'Open an overlay', function () use ($ui, $state): void {
            $state->overlays++;
            $pane = $ui->overlay('notice-'.$state->overlays);
            $pane->label('title', 'An overlay', 'title');
            $pane->label('text', 'It sits over the page. Close it to go back.', 'body');
            $pane->button('close', 'Close', fn () => $pane->hide());
        });
    }

    /**
     * A canvas: the toolkit lays it out and reports its size, and a graphics engine draws what it
     * shows. The handler runs once the canvas has a size and again whenever the window resizes it.
     *
     * $page->engine() is the engine the application draws its canvases with: the presentation
     * config's PRESENTATION_CANVAS_DRIVER, which left unset is the Graphics default. A view asks
     * the builder for it, and never asks Gfx for an engine itself. surface() gives a surface of the
     * stage's size in pixels; render() draws one frame on it; present() shows it on the stage. A
     * stage on a dense display has more pixels than points, so the frame is scaled by pixelScale()
     * and drawn in points.
     *
     * The wave moves: Play starts a timer on the event loop that draws a frame each time it fires,
     * and Stop forgets it, which leaves the last frame showing. The surface is kept between frames.
     *
     * The rate buttons set how often the timer fires: 30, 60, 120 or 240 times a second, or "uncapped",
     * which asks for 100,000 so a frame is drawn on every turn of the loop. The rate shown is frames
     * drawn a second: drawn below the target, the engine is the limit. Uncapped keeps a CPU core busy.
     *
     * When the engine casts rays (it is a CastsRays whose has(Feature::RAY_CASTING) is true: Velvet's with a raycasting
     * module installed), three more buttons
     * choose what fills the canvas. Both of the new scenes cast rays through a grid of walls, and
     * differ in what they do with where a ray stops:
     *
     *  - "Raycast a room": one ray a screen column from a turning camera, and the distance to the wall
     *    is how tall that column's slice of wall is drawn. The raycaster writes the picture straight
     *    onto the surface: nothing is drawn through a context.
     *  - "Cast shadows": the grid seen from above, with a light moving through it. Rays go out from
     *    the light and stop at the first block: the points where they stop outline what the light
     *    reaches. The context fills that outline, and whatever is outside it is in shadow.
     */
    private function graphics(ViewBuilder $page): void
    {
        $card = $page->card('graphics', 'Graphics');
        $engine = $page->engine();
        $card->label('about', fn (): string => 'Drawn by the "'.$engine->name().'" engine on the '.($engine->gpu() ? 'GPU' : 'CPU').', shown in a canvas.', 'body');

        /** @var DrawingSurface|null $surface on the canvas, at its current size */
        $surface = null;
        $scale = 1.0;
        $phase = 0.0;
        $drawn = 0;          // frames since the rate was last worked out
        $since = microtime(true);
        $rate = null;        // frames drawn a second over the last second; 0.0 until the first second is up, null while stopped
        $target = 30;                  // frames a second asked for; 0 is uncapped
        $last = microtime(true);       // when the wave last moved
        $scene = 'shapes';             // what fills the canvas: 'shapes', 'room' or 'light'

        // Raycasting is switched off until the raycasting slice brings it onto Graphics' port: nothing below that
        // casts rays runs, and the scene buttons are not shown. The code stays as it was, to come back on.
        $raycasting = false;
        [$caster, $lamp, $floor, $blocks, $corners, $scenery] = [null, null, null, [], [], null];

        if ($raycasting) {
        // the room: 0 is open floor, a number is a wall of that kind. Made once; null when the engine has no raycaster
        $caster = $engine instanceof CastsRays && $engine->has(Feature::RAY_CASTING) ? $engine->raycaster(Grid::of([
            [1, 1, 1, 1, 1, 1, 1, 1, 1],
            [1, 0, 0, 0, 0, 0, 0, 0, 1],
            [1, 0, 2, 0, 0, 0, 3, 0, 1],
            [1, 0, 0, 0, 0, 0, 0, 0, 1],
            [1, 0, 0, 0, 0, 0, 0, 0, 1],
            [1, 0, 3, 0, 0, 0, 2, 0, 1],
            [1, 0, 0, 0, 0, 0, 0, 0, 1],
            [1, 1, 1, 1, 1, 1, 1, 1, 1],
        ])) : null;
        // what each kind of wall is painted with, then the ceiling, the floor, and how dark far walls get
        $scenery = new Scenery([1 => 0x8A9BA8FF, 2 => 0xFF6B35FF, 3 => 0x40C8FFFF], 0x101820FF, 0x2B3A42FF, 150);

        // the floor the light moves over, seen from above: 0 is open, a number is a block of that kind
        $floor = Grid::of([
            [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1],
            [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2, 0, 0, 0, 3, 0, 0, 0, 1],
            [1, 0, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1],
            [1, 0, 0, 0, 0, 0, 0, 2, 0, 0, 0, 3, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1],
            [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 3, 3, 0, 0, 0, 2, 0, 0, 0, 0, 0, 0, 1],
            [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2, 0, 1],
            [1, 0, 0, 0, 2, 0, 0, 0, 0, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1],
            [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1],
        ]);
        $lamp = is_null($caster) ? null : $engine->raycaster($floor);

        // the blocks inside the outer wall, and every corner a shadow's edge can start from: each block's
        // four, and the four where the outer walls meet
        [$blocks, $corners] = [[], []];
        for ($y = 1; $y < $floor->height - 1; $y++) {
            for ($x = 1; $x < $floor->width - 1; $x++) {
                if (($kind = $floor->wall($x, $y)) !== 0) {
                    $blocks[] = [$x, $y, $kind];
                    foreach ([[$x, $y], [$x + 1, $y], [$x, $y + 1], [$x + 1, $y + 1]] as $corner) {
                        $corners[implode(',', $corner)] = $corner;
                    }
                }
            }
        }
        foreach ([[1, 1], [$floor->width - 1, 1], [1, $floor->height - 1], [$floor->width - 1, $floor->height - 1]] as $corner) {
            $corners[implode(',', $corner)] = $corner;
        }
        }

        // the light scene. Everything is drawn in cells: the context's transform sizes a cell to the canvas
        $light = function (Canvas $g, float $width, float $height) use ($floor, $lamp, $blocks, $corners, &$scale, &$phase): void {
            // where the light is: round and round the middle block
            [$lx, $ly] = [12.0 + 8.0 * cos($phase * 0.4), 4.0 + 1.6 * sin($phase * 0.4)];

            // a shadow's edge runs from the light past a corner, so one ray goes just either side of every
            // corner: one stops at the block, the other slips by to whatever is behind it. In order
            // round the light, the points where the rays stop are the outline of what is lit
            $angles = [];
            foreach ($corners as [$cx, $cy]) {
                $toward = atan2($cy - $ly, $cx - $lx);
                array_push($angles, $toward - 0.0001, $toward + 0.0001);
            }
            sort($angles);

            // every ray in one call: an engine on the GPU casts them all in one trip there and back
            $lit = new Path;
            foreach ($lamp->casts($lx, $ly, $angles) as $i => $hit) {
                $angle = $angles[$i];
                [$x, $y] = [$lx + $hit->distance * cos($angle), $ly + $hit->distance * sin($angle)];
                $i === 0 ? $lit->moveTo($x, $y) : $lit->lineTo($x, $y);
            }
            $lit->close();

            $cell = min($width / $floor->width, $height / $floor->height);
            $g->clear(Color::hex('#05070a'));
            $g->scale($scale, $scale);
            $g->translate(($width - $floor->width * $cell) / 2, ($height - $floor->height * $cell) / 2);
            $g->scale($cell, $cell);

            // the floor in the dark, then what the light reaches: brightest at the light, fading with distance
            $g->fillRect(1, 1, $floor->width - 2, $floor->height - 2, Color::hex('#141b22'));
            $g->fillPath($lit, FillRule::NON_ZERO, new RadialGradient($lx, $ly, 14.0, [
                [0.0, Color::rgba(255, 236, 170, 0.95)],
                [1.0, Color::rgba(255, 236, 170, 0.10)],
            ]));

            // the walls and blocks over it, then the light itself
            $wall = Color::hex('#8a9ba8');
            $g->fillRect(0, 0, $floor->width, 1, $wall);
            $g->fillRect(0, $floor->height - 1, $floor->width, 1, $wall);
            $g->fillRect(0, 1, 1, $floor->height - 2, $wall);
            $g->fillRect($floor->width - 1, 1, 1, $floor->height - 2, $wall);
            foreach ($blocks as [$x, $y, $kind]) {
                $g->fillRect($x, $y, 1, 1, Color::hex($kind === 2 ? '#ff6b35' : '#40c8ff'));
            }
            $g->fillEllipse($lx, $ly, 0.22, 0.22, Color::hex('#fff7d6'));
        };

        $draw = function () use (&$surface, &$scale, &$phase, &$drawn, &$scene, $caster, $scenery, $light): void {
            if (is_null($surface)) {
                return;
            }
            $drawn++;

            if ($scene === 'room') {
                // a camera in the middle of the room, turning: the raycaster paints what it sees
                $caster->paint(new Camera(4.5, 4.0, $phase * 0.25), $scenery, $surface);
                $surface->present();

                return;
            }
            [$width, $height] = $surface->size();
            [$width, $height] = [$width / $scale, $height / $scale];

            if ($scene === 'light') {
                $surface->draw(fn (Canvas $g) => $light($g, $width, $height))->present();

                return;
            }

            $surface->draw(function (Canvas $g) use ($scale, $width, $height, $phase): void {
                $g->clear(Color::hex('#101820'));
                $g->scale($scale, $scale);

                // filled shapes: a rounded panel, a circle over it, half see-through
                $g->fillRoundRect(16, 16, 150, $height - 32, 14, Color::hex('#ff6b35'));
                $g->fillEllipse(166, $height / 2, 52, 52, Color::rgba(64, 200, 255, 0.6));

                // a stroked sine wave, the width of the canvas: $phase slides it along
                $wave = new Path;
                for ($x = 240.0; $x <= $width - 16; $x += 6.0) {
                    $y = $height / 2 + 40 * sin($x / 28 - $phase);
                    $x === 240.0 ? $wave->moveTo($x, $y) : $wave->lineTo($x, $y);
                }
                $g->strokePath($wave, 4.0, Color::hex('#f7f052'));
            })->present();
        };

        // a surface follows its stage's size frame by frame: one is made per stage, kept while the stage is
        $shown = null;
        $card->canvas('scene', 180, function (ActorStage $stage) use (&$surface, &$scale, &$shown, $draw, $engine): void {
            if ($shown !== $stage) {
                $surface = $engine->surface($stage);
                $shown = $stage;
            }
            $scale = $stage->pixelScale();
            $draw();
        });

        // a frame clock under a name replaces the one of that name, so Play twice is one clock; a frame
        // is drawn only while the kitchen sink is the page showing
        $play = function () use ($draw, &$target, &$last, &$phase, &$drawn, &$since, &$rate): void {
            [$drawn, $since, $last, $rate] = [0, microtime(true), microtime(true), 0.0];
            app(Live::class)->refresh();

            // uncapped (0): a frame every turn of the loop, as often as one can be drawn
            FrameClock::run('kitchen-sink.wave', (float) $target, function () use ($draw, &$last, &$phase, &$drawn, &$since, &$rate): void {
                if (app(Navigator::class)->current() !== '/kitchen-sink') {
                    return;
                }
                // by the clock, not by the frame: the wave moves at one speed whatever the rate
                $now = microtime(true);
                $phase += ($now - $last) * 3.0;
                $last = $now;
                $draw();

                // once a second: the rate over that second, and the label that shows it read again
                $elapsed = microtime(true) - $since;
                if ($elapsed >= 1.0) {
                    $rate = $drawn / $elapsed;
                    [$drawn, $since] = [0, microtime(true)];
                    app(Live::class)->refresh();
                }
            });
        };
        $stop = function () use (&$rate): void {
            FrameClock::stop('kitchen-sink.wave');
            $rate = null;
            app(Live::class)->refresh();
        };

        $row = $card->row('controls', 8);
        $row->button('play', 'Play', $play);
        $row->button('stop', 'Stop', $stop);
        $row->label('target', function () use (&$target): string {
            return 'Target: '.($target ?: 'uncapped');
        });

        // one button a rate. Playing: the timer is replaced by one at the new rate. Stopped: the
        // next Play uses it.
        $rates = $card->row('rates', 8);
        foreach ([30 => '30', 60 => '60', 120 => '120', 240 => '240', 0 => 'Uncapped'] as $frames => $label) {
            $rates->button("rate-{$frames}", $label, function () use ($frames, &$target, &$rate, $play): void {
                $target = $frames;
                is_null($rate) ? app(Live::class)->refresh() : $play();
            });
        }

        if (! is_null($caster)) {
            $what = $card->row('what', 8);
            foreach (['shapes' => 'Shapes', 'room' => 'Raycast a room', 'light' => 'Cast shadows'] as $name => $label) {
                $what->button($name, $label, function () use ($name, &$scene, $draw): void {
                    $scene = $name;
                    $draw();
                });
            }
        }

        // a Closure with use (&$rate): an arrow function would keep the value $rate had when it was made
        $row->label('fps', function () use (&$rate): string {
            return match (true) {
                is_null($rate) => 'Stopped',
                $rate === 0.0 => 'Measuring…',
                default => sprintf('%.0f frames a second', $rate),
            };
        });

        $play();
    }
}
