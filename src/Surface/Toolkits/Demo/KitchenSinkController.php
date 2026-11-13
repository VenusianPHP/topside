<?php

namespace Surface\Toolkits\Demo;

use ReflectionException;
use Surface\Contracts\Toolkits\Mail\Views\ButtonClicked;
use Surface\Toolkits\Navigation\Navigator;
use Surface\Toolkits\View;

/**
 * The controller of the kitchen sink page: what the route "/kitchen-sink" opens.
 *
 * How a page comes up, start to finish:
 *
 *   1. A route names a controller method. See routes/navigation.php:
 *        Route::get('/kitchen-sink', [KitchenSinkController::class, 'index']);
 *   2. Something goes to the route: Navigator::go('/kitchen-sink'), or a button whose handler is
 *      the route's name (the button on the welcome page does this).
 *   3. The Navigator calls the method, here index(), and remembers where it came from.
 *   4. The method asks for a view. The view says what is on the page (KitchenSinkView).
 *
 * A controller is also a place for what widgets do. A view names a method here as a handler:
 *
 *        $ui->button('back', 'Back', [KitchenSinkController::class, 'back']);
 *
 * and the method is called with the mail the widget posted. The container makes a new controller
 * for each call, so a controller keeps nothing between calls: what must last lives in a service
 * of the application.
 */
class KitchenSinkController
{
    /**
     * Opens the page. The view is built the first time and shown as it is after that.
     *
     * @throws ReflectionException
     */
    public function index(): void
    {
        View::render(KitchenSinkView::class);
    }

    /**
     * The Back button's handler: the route before this one. The Navigator keeps the history,
     * so the page does not know where "back" is.
     *
     * @throws ReflectionException
     */
    public function back(ButtonClicked $clicked): void
    {
        app(Navigator::class)->back();
    }

    /**
     * Prints the mail a click posted. Every widget is known by its window and its path in the
     * view, and that is all a handler needs to know which one was used.
     */
    public function inspect(ButtonClicked $clicked): void
    {
        dump([
            'signal' => $clicked->name(),
            'window' => $clicked->window,
            'path' => $clicked->path,
            'uuid' => $clicked->uuid,
        ]);
    }
}
