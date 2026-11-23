<?php

declare(strict_types=1);

use Surface\Toolkits\View;
use Surface\Toolkits\Presentation;
use Surface\Toolkits\ToolkitShell;
use Surface\Toolkits\ToolkitAdapters;
use Surface\Contracts\Toolkits\Mail\PrimitiveMail;
use Venusian\Topside\Tests\Fakes\FakeLoop;
use Venusian\Topside\Tests\Fakes\UI\FakeButton;
use Venusian\Topside\Tests\Fakes\UI\FakeColumn;
use Venusian\Topside\Tests\Fakes\Views\ScriptedView;

/*
| Helpers for view tests. A view goes through the same path an application's does: the shell
| opens the window, View::render() puts a component on screen, a native event posts mail to
| the loop, and the presentation driver delivers it to the view. The toolkit is the fake one,
| whose primitives record every hook they are told.
*/

/** Run the application with a fake loop and open its window, as a toolkit application does. */
function openViewWindow(): void
{
    test()->loop = new FakeLoop();
    test()->app->markApplicationRunning();
    test()->app->registerSingleton('event-loop', fn () => test()->loop);
    test()->app->make('event-loop');

    (new ToolkitShell())->open();
    ScriptedView::$describe = null;
}

/** The window's content column. */
function screen(): FakeColumn
{
    return Presentation::driver()->window()->content();
}

/** The primitive at $path under the window's content column. */
function at(string $path): mixed
{
    return screen()->view($path) ?? throw new RuntimeException("Nothing at '{$path}'.");
}

/** Names of the children of the container at $path, in order. */
function childNames(string $path): array
{
    return array_map(fn ($child) => $child->name(), at($path)->children());
}

/** Click a button the way the user does, and deliver what that posted as the loop would. */
function click(FakeButton $button): void
{
    $button->click();
    deliverPosted();
}

/** Deliver every primitive's mail the loop holds to the presentation driver. */
function deliverPosted(): void
{
    flushDrags();
    [$posted, test()->loop->posted] = [test()->loop->posted, []];
    foreach ($posted as $mail) {
        if ($mail instanceof PrimitiveMail) {
            Presentation::driver()->deliver($mail);
        }
    }
}

/** What the pump does as it returns: the latest mail of each drag is posted. */
function flushDrags(): void
{
    ToolkitAdapters::driver()->session()->flushLatest();
}

/** Primitives built so far, of every fake kind. */
function built(): int
{
    $total = 0;
    foreach (get_declared_classes() as $class) {
        if (str_starts_with($class, 'Venusian\\Topside\\Tests\\Fakes\\UI\\') && property_exists($class, 'built')) {
            $total += $class::$built;
        }
    }

    return $total;
}

/** Render ScriptedView with $describe, then re-render it after each change to its state. */
function scripted(Closure $describe, array $props = []): ScriptedView
{
    ScriptedView::$describe = $describe;
    View::render(ScriptedView::class, $props);

    return Presentation::driver()->view()->component;
}

function rerender(): void
{
    Presentation::driver()->view()->render();
}
