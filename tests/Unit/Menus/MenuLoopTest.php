<?php

declare(strict_types=1);

use Surface\Toolkits\View;
use Surface\Toolkits\ToolkitShell;
use Surface\Toolkits\Presentation;
use Surface\Toolkits\Menus\Item;
use Surface\Toolkits\Menus\MenuBar;
use Surface\Core\Actions\Bootstrap\LoadSignals;
use Voyager\IOPools\IOPoolsServiceProvider;
use Voyager\Core\RenderedInstance;
use Venusian\Topside\Tests\Fakes\Views\MenuView;

it('carries a click through the real event loop: callback, render, then mail', function (): void {
    $framework = dirname((new ReflectionClass(RenderedInstance::class))->getFileName(), 4);
    $pools = require "{$framework}/config/io-pools.php";
    $pools['event_loop']['mail_handlers']['default'] = 'signal';
    $app = topside([
        'io-pools' => $pools,
        'toolkits' => ['windows' => ['default_menu' => 'app', 'menus' => ['app' => fileBar()]]],
    ]);
    $app->markApplicationRunning();
    $provider = new IOPoolsServiceProvider($app);
    $provider->register();
    $provider->boot();
    $loop = $app['event-loop'];
    (new LoadSignals())->bootstrap($app);

    (new ToolkitShell())->open();
    $order = [];
    $run = function () use (&$order): void {
        $order[] = 'callback';
    };
    MenuView::$onButton = null;
    MenuView::$menus = fn ($view, MenuBar $bar) => $bar->item('file.save', fn (Item $save) => $save->onClick($run));
    View::render(MenuView::class);
    $view = Presentation::driver()->view()->component;

    $app['signals']->listen('menu.clicked.file.save', function () use (&$order, $loop, $view): void {
        $order[] = "mail after {$view->renders} renders";
        $loop->stop();
    });
    $loop->at(0.01, fn () => nativeBar()->choose('File/Save'));
    $loop->at(2.0, fn () => $loop->stop(1));

    expect($loop->run())->toBe(0)
        ->and($order[0])->toBe('callback')
        ->and($order[1])->toBe('mail after 2 renders');
});
