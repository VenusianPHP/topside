<?php

declare(strict_types=1);

use Surface\Toolkits\View;
use Surface\Toolkits\Navigation\Visit;
use Surface\Toolkits\Navigation\Navigator;
use Surface\Toolkits\Navigation\ToolkitPageRoute as Route;
use Surface\Toolkits\Views\Elements\Button;
use Surface\Toolkits\Views\Elements\Column;
use Venusian\Topside\Tests\Fakes\Views\ScriptedView;

/*
| A route's middleware runs round its controller each time the navigator opens it, on go(),
| back() and forward() alike. A middleware is a class with handle(Visit, Closure $next),
| resolved from the container, or a closure taking the same two. One that does not call $next
| puts its own view on screen; the route is the current one all the same.
*/

/** What ran, in order, in the test under way. */
class MiddlewareTrail
{
    /** @var list<string> */
    public static array $ran = [];
}

/** A page that says which route it is and the arguments it was given. */
class MiddlewarePageController
{
    public function show(string $who = 'nobody'): void
    {
        MiddlewareTrail::$ran[] = "controller({$who})";
        ScriptedView::$describe = fn () => Column::make()->with(Button::make("page:{$who}"));
        View::render(ScriptedView::class);
    }
}

class OuterMiddleware
{
    public function handle(Visit $visit, Closure $next): mixed
    {
        MiddlewareTrail::$ran[] = 'outer>';
        $answer = $next($visit);
        MiddlewareTrail::$ran[] = '<outer';

        return $answer;
    }
}

class InnerMiddleware
{
    public function handle(Visit $visit, Closure $next): mixed
    {
        MiddlewareTrail::$ran[] = 'inner>';
        $answer = $next($visit);
        MiddlewareTrail::$ran[] = '<inner';

        return $answer;
    }
}

/** Stops the visit and shows its own page in the route's place. */
class StoppingMiddleware
{
    public function handle(Visit $visit, Closure $next): mixed
    {
        MiddlewareTrail::$ran[] = "stopped({$visit->name})";
        ScriptedView::$describe = fn () => Column::make()->with(Button::make('stopped'));
        View::render(ScriptedView::class);

        return null;
    }
}

/** Takes the parameters written after the class name. */
class ParameterMiddleware
{
    public function handle(Visit $visit, Closure $next, string ...$parameters): mixed
    {
        MiddlewareTrail::$ran[] = 'parameters('.implode(',', $parameters).')';

        return $next($visit);
    }
}

/** Built by the container: the trail it writes to is the one the test bound. */
class ResolvedMiddleware
{
    public function __construct(public readonly ArrayObject $seen) {}

    public function handle(Visit $visit, Closure $next): mixed
    {
        $this->seen->append($visit);

        return $next($visit);
    }
}

beforeEach(function (): void {
    openViewWindow();
    MiddlewareTrail::$ran = [];
});

it('runs a route\'s middleware in order round its controller', function (): void {
    Route::get('/mw-open', [MiddlewarePageController::class, 'show'])
        ->middleware(OuterMiddleware::class, InnerMiddleware::class);

    app(Navigator::class)->go('/mw-open', ['who' => 'open']);

    expect(MiddlewareTrail::$ran)->toBe(['outer>', 'inner>', 'controller(open)', '<inner', '<outer'])
        ->and(at('column1.button1')->label())->toBe('page:open');
});

it('adds to a route\'s middleware each time it is given, one by one or in a list', function (): void {
    Route::get('/mw-added', [MiddlewarePageController::class, 'show'])
        ->middleware([OuterMiddleware::class])
        ->middleware(InnerMiddleware::class);

    expect(Route::named('/mw-added')->middlewareGiven())->toBe([OuterMiddleware::class, InnerMiddleware::class]);
});

it('gives the middleware the visit: the route\'s name, its arguments and the navigator', function (): void {
    $seen = new ArrayObject();
    app()->registerInstance(ResolvedMiddleware::class, new ResolvedMiddleware($seen));
    Route::get('/mw-visit', [MiddlewarePageController::class, 'show'])->middleware(ResolvedMiddleware::class);

    app(Navigator::class)->go('/mw-visit', ['who' => 'visit']);

    expect($seen)->toHaveCount(1)
        ->and($seen[0]->name)->toBe('/mw-visit')
        ->and($seen[0]->args)->toBe(['who' => 'visit'])
        ->and($seen[0]->navigator)->toBe(app(Navigator::class));
});

it('calls the controller with the arguments of the visit the middleware passes on', function (): void {
    Route::get('/mw-changed', [MiddlewarePageController::class, 'show'])
        ->middleware(static fn (Visit $visit, Closure $next) => $next(new Visit($visit->name, ['who' => 'changed'], $visit->navigator)));

    app(Navigator::class)->go('/mw-changed', ['who' => 'given']);

    expect(MiddlewareTrail::$ran)->toBe(['controller(changed)']);
});

it('gives a middleware the parameters written after its name', function (): void {
    Route::get('/mw-parameters', [MiddlewarePageController::class, 'show'])
        ->middleware(ParameterMiddleware::class.':one,two');

    app(Navigator::class)->go('/mw-parameters');

    expect(MiddlewareTrail::$ran)->toBe(['parameters(one,two)', 'controller(nobody)']);
});

it('shows what a middleware that stops the visit shows, with the route current and the history as for any route', function (): void {
    Route::get('/mw-before', [MiddlewarePageController::class, 'show']);
    Route::get('/mw-stopped', [MiddlewarePageController::class, 'show'])->middleware(StoppingMiddleware::class);

    app(Navigator::class)->go('/mw-before', ['who' => 'before']);
    MiddlewareTrail::$ran = [];
    app(Navigator::class)->go('/mw-stopped');

    expect(MiddlewareTrail::$ran)->toBe(['stopped(/mw-stopped)'])
        ->and(at('column1.button1')->label())->toBe('stopped')
        ->and(app(Navigator::class)->current())->toBe('/mw-stopped')
        ->and(app(Navigator::class)->canGoBack())->toBeTrue();

    app(Navigator::class)->back();

    expect(at('column1.button1')->label())->toBe('page:before');
});

it('opens a stopped route again with go(), running its middleware again, without adding to the history', function (): void {
    Route::get('/mw-again', [MiddlewarePageController::class, 'show'])->middleware(OuterMiddleware::class);

    app(Navigator::class)->go('/mw-again', ['who' => 'once']);
    app(Navigator::class)->go('/mw-again', ['who' => 'twice']);

    expect(MiddlewareTrail::$ran)->toBe(['outer>', 'controller(once)', '<outer', 'outer>', 'controller(twice)', '<outer'])
        ->and(app(Navigator::class)->canGoBack())->toBeFalse();
});

it('runs the middleware on back() and forward() too', function (): void {
    Route::get('/mw-one', [MiddlewarePageController::class, 'show'])->middleware(OuterMiddleware::class);
    Route::get('/mw-two', [MiddlewarePageController::class, 'show']);

    app(Navigator::class)->go('/mw-one', ['who' => 'one']);
    app(Navigator::class)->go('/mw-two', ['who' => 'two']);
    MiddlewareTrail::$ran = [];

    app(Navigator::class)->back();
    app(Navigator::class)->forward();
    app(Navigator::class)->back();

    expect(MiddlewareTrail::$ran)->toBe([
        'outer>', 'controller(one)', '<outer',
        'controller(two)',
        'outer>', 'controller(one)', '<outer',
    ]);
});

it('gives a group\'s middleware to every route it declares, before each route\'s own', function (): void {
    Route::middleware(OuterMiddleware::class)->group(function (): void {
        Route::get('/mw-grouped', [MiddlewarePageController::class, 'show'])->middleware(InnerMiddleware::class);
    });

    app(Navigator::class)->go('/mw-grouped', ['who' => 'grouped']);

    expect(MiddlewareTrail::$ran)->toBe(['outer>', 'inner>', 'controller(grouped)', '<inner', '<outer']);
});

it('nests groups, the outer group\'s middleware first, and gives none to a route declared after them', function (): void {
    Route::middleware([OuterMiddleware::class])->group(function (): void {
        Route::middleware(InnerMiddleware::class)->middleware(ParameterMiddleware::class.':nested')->group(function (): void {
            Route::get('/mw-nested', [MiddlewarePageController::class, 'show']);
        });
        Route::get('/mw-outer-only', [MiddlewarePageController::class, 'show']);
    });
    Route::get('/mw-after', [MiddlewarePageController::class, 'show']);

    expect(Route::named('/mw-nested')->middlewareGiven())
        ->toBe([OuterMiddleware::class, InnerMiddleware::class, ParameterMiddleware::class.':nested'])
        ->and(Route::named('/mw-outer-only')->middlewareGiven())->toBe([OuterMiddleware::class])
        ->and(Route::named('/mw-after')->middlewareGiven())->toBe([]);
});

it('ends a group when the routes it declares throw', function (): void {
    expect(fn () => Route::middleware(OuterMiddleware::class)->group(function (): void {
        throw new RuntimeException('a routes file failed');
    }))->toThrow(RuntimeException::class, 'a routes file failed');

    Route::get('/mw-after-failure', [MiddlewarePageController::class, 'show']);

    expect(Route::named('/mw-after-failure')->middlewareGiven())->toBe([]);
});

it('says a route or its class has no other method than middleware()', function (): void {
    $route = Route::get('/mw-unknown', [MiddlewarePageController::class, 'show']);

    expect(fn () => $route->guard())
        ->toThrow(BadMethodCallException::class, 'Call to undefined method Surface\Toolkits\Navigation\ToolkitPageRoute::guard()')
        ->and(fn () => Route::guard())
        ->toThrow(BadMethodCallException::class, 'Call to undefined method Surface\Toolkits\Navigation\ToolkitPageRoute::guard()');
});
