<?php

namespace Surface\Toolkits\Drivers;

use ReflectionException;
use Surface\Toolkits\ToolkitAdapters;
use Surface\Toolkits\ToolkitException;
use Surface\Toolkits\Views\Component;
use Surface\Toolkits\Managers\MenuManager;
use Surface\Toolkits\Views\Mount;
use Surface\Toolkits\Views\MountedView;
use Surface\Toolkits\Navigation\Navigator;
use Surface\Contracts\Toolkits\UI\TKColumn;
use Voyager\Contracts\Vessel\TheServiceContainer;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\Mail\PrimitiveMail;
use Surface\Contracts\Toolkits\Dialogs\Alert;
use Surface\Contracts\Toolkits\Mail\Windows\AlertAnswered;
use Surface\Contracts\Toolkits\Dialogs\SaveRequest;
use Surface\Contracts\Toolkits\Mail\Windows\SaveAnswered;

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

    /** True while a handler runs or a view renders: a refresh() then is already covered. */
    protected bool $busy = false;

    /** @var array<string, array{MountedView, \Closure}> each open alert's and save panel's view and handler, by its id */
    protected array $alerts = [];

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
        $this->alerts = [];
        $mounted = null;
        $mount = new Mount($this->navigator(), $this->window, function () use (&$mounted): void {
            $this->refresh($mounted);
        }, function (Alert $alert, \Closure $answered) use (&$mounted): string {
            $id = $this->window->alert($alert);
            $this->alerts[$id] = [$mounted, $answered];

            return $id;
        }, function (SaveRequest $request, \Closure $answered) use (&$mounted): string {
            $id = $this->window->saveFile($request);
            $this->alerts[$id] = [$mounted, $answered];

            return $id;
        });
        $mounted = $this->view = new MountedView($view, $this->root, $mount);
        $this->settle(function () use ($mounted, $view): void {
            $view->mounted();
            // mounted() may have put another view on screen: this one is then not shown.
            if ($this->view === $mounted) {
                $mounted->render();
            }
        });
        if ($this->view !== $mounted) {
            return;
        }

        app(MenuManager::class)->showsView($this->window->name(), $view, function () use ($mounted): void {
            if ($this->view === $mounted) {
                $this->settle(fn () => $this->renderAgain($mounted));
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

        $this->settle(function () use ($mail, $handler, $view): void {
            app(MenuManager::class)->guarded($mail->window(), static fn () => $handler($mail));

            if ($this->view === $view) {
                $this->renderAgain($view);
            }
        });
    }

    /**
     * Run the handler the on-screen view gave with the alert or save panel $mail answers, then
     * render the view again, as deliver() does. Nothing for one of a view no longer on screen.
     */
    public function answered(AlertAnswered|SaveAnswered $mail): void
    {
        if ($mail->window() !== $this->window?->name()) {
            return;
        }

        [$view, $handler] = $this->alerts[$mail->id] ?? [null, null];
        unset($this->alerts[$mail->id]);
        if (is_null($handler) || $view !== $this->view) {
            return;
        }

        $this->settle(function () use ($mail, $handler, $view): void {
            app(MenuManager::class)->guarded($mail->window(), static fn () => $handler($mail));

            if ($this->view === $view) {
                $this->renderAgain($view);
            }
        });
    }

    /**
     * A view's refresh(): render it again, the same as after a handler. Nothing when it is no
     * longer the view on screen, or while a handler or a render is under way (that ends with
     * the view shown as it is).
     */
    protected function refresh(?MountedView $view): void
    {
        if ($this->busy || is_null($view) || $this->view !== $view || ! $view->isMounted()) {
            return;
        }

        $this->settle(fn () => $this->renderAgain($view));
    }

    /**
     * Run $work with refresh() held off, however it ends.
     */
    protected function settle(\Closure $work): void
    {
        $was = $this->busy;
        $this->busy = true;

        try {
            $work();
        } finally {
            $this->busy = $was;
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
