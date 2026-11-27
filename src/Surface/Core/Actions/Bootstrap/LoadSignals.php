<?php

namespace Surface\Core\Actions\Bootstrap;

use ReflectionException;
use Surface\Contracts\Toolkits\Mail\QuitRequested;
use Surface\Contracts\Toolkits\Mail\PrimitiveMail;
use Surface\Contracts\Toolkits\Mail\Menus\MenuMail;
use Surface\Contracts\Toolkits\Mail\Windows\AlertAnswered;
use Surface\Contracts\Toolkits\Mail\Windows\SaveAnswered;
use Surface\Toolkits\Managers\MenuManager;
use Surface\Toolkits\Menus\Runtime\MenuNodeActivated;
use Surface\Toolkits\Managers\PresentationTreeManager;
use Voyager\Contracts\Vessel\TheServiceContainer as Application;

class LoadSignals
{
    /**
     * @throws ReflectionException
     */
    public function bootstrap(Application $app): void
    {
        // @todo - make this togglable via main.presentation

        $name = config('app.name', 'Venusian');
        $app['signals']->listen( "window.closed.$name", fn() => signal(new QuitRequested()));

        // Signals are dispatched under each interface they implement, so this is every
        // primitive's mail: the on-screen view's handler for it runs.
        $app['signals']->listen(PrimitiveMail::class, function (PrimitiveMail $mail) use ($app): void {
            $app->make(PresentationTreeManager::class)->driver()->deliver($mail);
        });

        // An alert's or a save panel's answer runs the handler the view gave with it.
        $app['signals']->listen(AlertAnswered::class, function (AlertAnswered $mail) use ($app): void {
            $app->make(PresentationTreeManager::class)->driver()->answered($mail);
        });
        $app['signals']->listen(SaveAnswered::class, function (SaveAnswered $mail) use ($app): void {
            $app->make(PresentationTreeManager::class)->driver()->answered($mail);
        });

        $app['signals']->listen(MenuNodeActivated::class, function (MenuNodeActivated $mail) use ($app): void {
            $app->make(MenuManager::class)->deliver($mail);
        });
        $app['signals']->listen(MenuMail::class, function (MenuMail $mail) use ($app): void {
            $app->make(MenuManager::class)->heard($mail);
        });
    }
}
