<?php

declare(strict_types=1);

use Surface\Toolkits\View;
use Surface\Toolkits\Presentation;
use Surface\Toolkits\Views\Component;
use Surface\Toolkits\Views\ViewException;
use Surface\Toolkits\Navigation\Navigator;
use Surface\Toolkits\Navigation\ToolkitPageRoute as Route;
use Surface\Toolkits\Views\Elements\Button;
use Surface\Toolkits\Views\Elements\Column;
use Surface\Toolkits\Views\Elements\Element;
use Venusian\Topside\Tests\Fakes\Views\ScriptedView;

/*
| A navigator belongs to the window that shows routes. Every view mounted there, and every
| component inside one however deep, reaches that same navigator with $this->navigator(),
| and asking the container for a Navigator gives it too, so the current route and the
| history last across views.
*/

/** A link that knows whether its route is the one open: a sidebar entry. */
class NavigatorLink extends Component
{
    public string $route = '/';

    public function render(): Element
    {
        $lit = $this->navigator()->current() === $this->route ? '*' : '';

        return Button::make($lit.$this->route)->onClick(fn () => $this->navigator()->go($this->route));
    }
}

/** A page: a column holding a link to each route, as a layout's sidebar would. */
class NavigatorPageController
{
    public function show(): void
    {
        ScriptedView::$describe = fn () => Column::make()->with(
            NavigatorLink::make(route: '/nav-one'),
            NavigatorLink::make(route: '/nav-two'),
        );
        View::render(ScriptedView::class);
    }
}

beforeEach(function (): void {
    openViewWindow();
    Route::get('/nav-one', [NavigatorPageController::class, 'show']);
    Route::get('/nav-two', [NavigatorPageController::class, 'show']);
});

it('gives the container\'s Navigator as the window\'s own, every time it is asked', function (): void {
    $window = Presentation::driver()->navigator();

    expect(app(Navigator::class))->toBe($window)
        ->and(app(Navigator::class))->toBe($window);
});

it('keeps the current route and the history across separate asks', function (): void {
    app(Navigator::class)->go('/nav-one');
    app(Navigator::class)->go('/nav-two');

    expect(app(Navigator::class)->current())->toBe('/nav-two')
        ->and(app(Navigator::class)->canGoBack())->toBeTrue();

    app(Navigator::class)->back();

    expect(app(Navigator::class)->current())->toBe('/nav-one')
        ->and(app(Navigator::class)->canGoForward())->toBeTrue();
});

it('gives the page and every component inside it the window\'s navigator', function (): void {
    app(Navigator::class)->go('/nav-one');
    $page = Presentation::driver()->view()->component;

    expect($page->navigator())->toBe(Presentation::driver()->navigator())
        ->and(at('column1.navigatorLink1')->label())->toBe('*/nav-one')
        ->and(at('column1.navigatorLink2')->label())->toBe('/nav-two');
});

it('lights the route a nested component navigated to, on the page that route renders', function (): void {
    app(Navigator::class)->go('/nav-one');

    click(at('column1.navigatorLink2'));

    expect(app(Navigator::class)->current())->toBe('/nav-two')
        ->and(at('column1.navigatorLink1')->label())->toBe('/nav-one')
        ->and(at('column1.navigatorLink2')->label())->toBe('*/nav-two');

    click(at('column1.navigatorLink1'));

    expect(at('column1.navigatorLink1')->label())->toBe('*/nav-one')
        ->and(at('column1.navigatorLink2')->label())->toBe('/nav-two');
});

it('says a component that is not mounted has no navigator', function (): void {
    $loose = new NavigatorLink();

    expect(fn () => $loose->navigator())
        ->toThrow(ViewException::class, 'NavigatorLink is not mounted in a window, so it has no navigator.');
});
