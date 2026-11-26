<?php

namespace Surface\Toolkits\Views;

use Closure;
use ReflectionClass;
use Surface\Contracts\Toolkits\Dialogs\Alert;
use ReflectionProperty;
use Surface\Toolkits\Menus\MenuBar;
use Surface\Toolkits\Navigation\Navigator;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Toolkits\Views\Elements\Element;
use Surface\Toolkits\Views\Elements\ComponentElement;

/**
 * A view: its state, what it does, and what it looks like.
 *
 *  - State is its public properties. View::render(MyView::class, ['count' => 3]) sets them
 *    first, so they are its props too.
 *  - What it does is its own methods, given to an element as a handler:
 *    Button::make('Add')->onClick($this->add(...)).
 *  - What it looks like is render(), which describes the primitives from the state.
 *
 * After a handler runs, render() runs again and only what changed is changed on screen, so a
 * handler changes the state and nothing else. render() is called often: keep it to reading
 * the state, with no work of its own.
 *
 * A component is built through the container, so its constructor may ask for services.
 */
abstract class Component
{
    /** What this is mounted in; set when it is mounted. */
    private ?Mount $mount = null;

    abstract public function render(): Element;

    /**
     * Called once, as the view is put on screen and before it is first shown: start what the
     * view loads here (a request, a timer). What it sets is in the first render; what comes
     * back later calls refresh(). The window and its navigation are there already. A
     * component used inside another is not called.
     *
     *     protected function mounted(): void
     *     {
     *         $this->loading = true;
     *         $this->library->search($this->query, fn (array $found) => $this->show($found));
     *     }
     */
    public function mounted(): void {}

    /**
     * The navigation of the window this component is on screen in: the same one for the page
     * and every component inside it, however deep, with nothing passed down.
     *
     *     public function lit(string $route): bool
     *     {
     *         return $this->navigator()->current() === $route;
     *     }
     *
     * @throws ViewException When the component is not mounted in a window.
     */
    public function navigator(): Navigator
    {
        return $this->mount?->navigator ?? throw new ViewException(static::class.' is not mounted in a window, so it has no navigator.');
    }

    /**
     * The window this component is on screen in, the same one for the page and every
     * component inside it:
     *
     *     $this->window()->setTitle($this->story->title);
     *
     * @throws ViewException When the component is not mounted in a window.
     */
    public function window(): ToolkitPoweredWindow
    {
        return $this->mount?->window ?? throw new ViewException(static::class.' is not mounted in a window.');
    }

    /**
     * Show the view again after its state changed outside a handler: work that finished on
     * the event loop, a request that came back. A handler needs no call, the view is shown
     * again after it anyway; neither does render(), which only reads the state.
     *
     *     $request->then(function (array $days): void {
     *         $this->days = $days;
     *         $this->refresh();
     *     });
     *
     * Nothing happens once the view is off screen, or for a component not mounted.
     */
    public function refresh(): void
    {
        if (! is_null($refresh = $this->mount?->refresh)) {
            $refresh();
        }
    }

    /**
     * Show $alert modal to the window, with the toolkit's own alert, and run $answered with
     * its answer, the same as a handler: the view is shown again after it. The answer is
     * AlertAnswered: $answer->button is the index of the button, the cancel index for Esc.
     *
     *     $this->alert(new Alert('Clear your key?', buttons: ['Clear', 'Cancel']), function (AlertAnswered $answer): void {
     *         if ($answer->button === 0) {
     *             $this->clear();
     *         }
     *     });
     *
     * $answered does not run once the view is off screen.
     *
     * @param Closure(\Surface\Contracts\Toolkits\Mail\Windows\AlertAnswered): void $answered
     * @return string the alert's id, the one its AlertAnswered carries
     * @throws ViewException When the component is not mounted in a window.
     * @throws \RuntimeException When the window already shows an alert.
     */
    public function alert(Alert $alert, Closure $answered): string
    {
        $show = $this->mount?->alert ?? throw new ViewException(static::class.' is not mounted in a window, so it can show no alert.');

        return $show($alert, $answered);
    }

    /**
     * @internal What the component is mounted in, given when it is mounted.
     */
    public function mountedIn(?Mount $mount): static
    {
        $this->mount = $mount;

        return $this;
    }

    /**
     * The bar of the window while this is the view on screen: given the bar the window, route
     * and controller came to, return the one to show. Called again after each handler, so
     * read the state here as render() does. A component used inside another is not asked.
     *
     *     return $bar->item('file.save', fn (Item $save) => $save->enabled($this->dirty));
     */
    public function menu(MenuBar $bar): MenuBar
    {
        return $bar;
    }

    /**
     * This component as an element of another view: Card::make(title: 'Hi')->with(...).
     * The named arguments are set on its public properties each time the view renders.
     */
    public static function make(mixed ...$props): ComponentElement
    {
        return new ComponentElement(static::class, $props);
    }

    /**
     * Set public properties from $props, by name.
     *
     * @param array<string, mixed> $props
     * @throws ViewException For a name that is not a public property of this view.
     */
    public function assign(array $props): static
    {
        $settable = self::settable(static::class);

        foreach ($props as $name => $value) {
            if (! in_array($name, $settable, true)) {
                $known = $settable === [] ? 'it has none' : 'it has '.implode(', ', $settable);
                throw new ViewException(static::class." has no public property '{$name}' to set: {$known}.");
            }

            $this->{$name} = $value;
        }

        return $this;
    }

    /**
     * @return list<string> the public, non-static properties of $class
     */
    private static function settable(string $class): array
    {
        $properties = (new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC);

        return array_values(array_map(
            static fn (ReflectionProperty $p): string => $p->getName(),
            array_filter($properties, static fn (ReflectionProperty $p): bool => ! $p->isStatic()),
        ));
    }
}
