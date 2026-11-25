<?php

namespace Surface\Toolkits\Drivers;

use ReflectionException;
use Surface\Toolkits\ToolkitAdapters;
use Surface\Toolkits\ToolkitException;
use Surface\Toolkits\Views\Component;
use Surface\Toolkits\Managers\MenuManager;
use Surface\Toolkits\Views\MountedView;
use Surface\Toolkits\Navigation\Navigator;
use Surface\Contracts\Toolkits\UI\TKColumn;
use Voyager\Contracts\Vessel\TheServiceContainer;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\Mail\PrimitiveMail;

class PresentationTreeDriver extends ContentTreeDriver
{
    protected bool $presented = false;

    /** The window's only content container: each view's root goes in it. */
    protected ?TKColumn $root = null;

    protected ?ToolkitPoweredWindow $window = null;

    /** The view on screen; one at a time. */
    protected ?MountedView $view = null;

    /** This window's navigation: its routes, its history; every view mounted here gets it. */
    protected ?Navigator $navigator = null;

    public function __construct(
        protected TheServiceContainer $app
    ) {}

    public function present(): void
    {
        $this->window->present();
        $this->presented = true;
    }

    /**
     * @throws ReflectionException
     */
    public function boot(int $width, int $height): void
    {
        $name = config('app.name', 'Venusian');
        $title = config('main.title', 'Venusian Topside');

        $this->window = ToolkitAdapters::windows()
            ->open($name, $width, $height)
            ->setTitle($title);
        app(MenuManager::class)->hosts($this->window->name());

        $this->root = $this->window->column('root');
        $this->root->fill();
    }

    /**
     * Put $view on screen in place of the one there: everything the old one made is removed
     * first.
     *
     * @throws ToolkitException When the window is not open yet.
     */
    public function render(Component $view): void
    {
        if (is_null($this->root)) {
            throw new ToolkitException('The window is not open: boot() the presentation before rendering a view.');
        }

        $this->view?->unmount();
        $mounted = $this->view = new MountedView($view, $this->root, $this->navigator());
        $mounted->render();

        app(MenuManager::class)->showsView($this->window->name(), $view, function () use ($mounted): void {
            if ($this->view === $mounted) {
                $this->renderAgain($mounted);
            }
        });
    }

    /**
     * Run the handler the on-screen view has for $mail, then render the view again so the
     * window shows what the handler changed. When the handler put another view on screen
     * (it navigated), the old one is not rendered again.
     */
    public function deliver(PrimitiveMail $mail): void
    {
        $view = $this->view;
        if (is_null($view) || $mail->window() !== $this->window?->name()) {
            return;
        }

        $handler = $view->handlerFor($mail);
        if (is_null($handler)) {
            return;
        }

        app(MenuManager::class)->guarded($mail->window(), static fn () => $handler($mail));

        if ($this->view === $view) {
            $this->renderAgain($view);
        }
    }

    /**
     * What is on screen after a handler: the view, then the menus it describes. Nothing,
     * when the handler closed the window.
     */
    protected function renderAgain(MountedView $view): void
    {
        if (! $this->window->isOpen()) {
            return;
        }

        $view->render();
        app(MenuManager::class)->refresh($this->window->name());
    }

    /**
     * The navigation of the window this presentation shows routes in. It lives as long as the
     * presentation, so its current route and history last across views.
     */
    public function navigator(): Navigator
    {
        return $this->navigator ??= new Navigator();
    }

    public function view(): ?MountedView
    {
        return $this->view;
    }

    public function window(): ?ToolkitPoweredWindow
    {
        return $this->window;
    }
}
