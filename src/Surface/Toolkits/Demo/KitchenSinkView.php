<?php

namespace Surface\Toolkits\Demo;

use Surface\Contracts\Toolkits\Composition;
use Surface\Contracts\Toolkits\ViewBuilder;
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

        $this->text($page);
        $this->buttons($page, $state, $refresh);
        $this->input($page, $state, $refresh);
        $this->rows($page);
        $this->cards($page);

        $page->label('later', 'Not on this page yet: image (it needs a file), search, tabs, reveal and overlay.', 'footnote');
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
}
