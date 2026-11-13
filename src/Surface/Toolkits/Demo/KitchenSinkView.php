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
}
