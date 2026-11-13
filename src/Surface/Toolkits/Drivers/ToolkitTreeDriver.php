<?php

namespace Surface\Toolkits\Drivers;

use ArrayObject;
use ReflectionException;
use Surface\Toolkits\Toolkits;
use Surface\Contracts\Toolkits\Composition;
use Surface\Toolkits\Factory\ToolkitUIBuilder;
use Surface\Toolkits\Support\Live;
use Surface\Contracts\Toolkits\Primitives\TKButton;
use Surface\Contracts\Toolkits\Primitives\TKColumn;
use Surface\Contracts\Toolkits\ToolkitWindow;
use Surface\Toolkits\Navigation\Navigator;
use Voyager\Contracts\Vessel\TheServiceContainer;

class ToolkitTreeDriver extends ContentTreeDriver
{

    protected ?TKButton $back = null;

    protected bool $presented = false;

    protected ?TKButton $forward = null;

    protected ?ToolkitWindow $window = null;

    /** The window's only content container: each view's root column goes in it. */
    protected ?TKColumn $root = null;

    /** @var array<class-string, TKColumn> the root column of each view built, by the view's class */
    protected array $views = [];


    /** Widgets the builders made hidden, waiting for the window to show. */
    protected ArrayObject $reveals;

    public function __construct(
        protected TheServiceContainer $app
    ) {
        $this->reveals = new ArrayObject();
    }

    /**
     * @throws ReflectionException
     */
    public function boot(int $width, int $height): void
    {
        $name = config('app.name', 'Venusian');
        $title = config('main.title', 'Venusian Topside');
        $this->window = Toolkits::window()
            ->open($name, $width, $height, 'main')
            ->setTitle($title);

        // The window's only content container: the deck on top, the footer under it.
        $this->root = $this->window->column('root');
        $this->root->fill();
    }

    /**
     * @throws ReflectionException
     */
    public function render(Composition $view, array $args): void
    {
        $navigator = app(Navigator::class);
        $this->back?->setEnabled($navigator->canGoBack());
        $this->forward?->setEnabled($navigator->canGoForward());

        // One view shows at a time: a hidden view takes no space.
        foreach ($this->views as $class => $root) {
            $root->setVisible($class === $view::class);
        }

        if (isset($this->views[$view::class])) {
            // A view built before is shown as it is: its live values catch up with what changed while it was hidden.
            app(Live::class)->refresh();

            return;
        }

        $root = $this->root->column($view->name());
        $root->fill();

        $content = $root->column('content', 12, 24);
        $content->fill();

        $view->compose(new ToolkitUIBuilder($content), $args);

        $this->views[$view::class] = $root;
    }

    public function present(): void
    {
        $this->window->present();
        $this->presented = true;
        $this->reveal();
    }

    /**
     * Make every waiting widget visible: each plays its appear transition.
     */
    protected function reveal(): void
    {
        foreach ($this->reveals as $widget) {
            $widget->setVisible(true);
        }

        $this->reveals->exchangeArray([]);
    }
}