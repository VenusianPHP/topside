<?php

namespace Surface\Core\Actions\Bootstrap;

use ReflectionException;
use Surface\Toolkits\Support\ViewActions;
use Surface\Contracts\Toolkits\Mail\Views\ButtonClicked;
use Surface\Contracts\Toolkits\Mail\Views\TextSubmitted;
use Surface\Contracts\Toolkits\Mail\Views\TextChanged;
use Surface\Contracts\Toolkits\Mail\Views\SelectionChanged;
use Surface\Contracts\Toolkits\Mail\Views\ValueChanged;
use Surface\Contracts\Toolkits\Mail\Views\ViewScrolled;
use Surface\Contracts\Toolkits\Mail\Views\ItemActivated;
use Surface\Contracts\Toolkits\Mail\Views\ItemActionClicked;
use Surface\Contracts\Toolkits\Mail\Views\GroupClicked;
use Voyager\Contracts\Vessel\TheServiceContainer as Application;

class LoadRoutingMailListeners
{
    /**
     * @throws ReflectionException
     */
    public function bootstrap(Application $app): void
    {
        $app['signals']->listen( ButtonClicked::class, function (ButtonClicked $clicked) use ($app): void {
            $app->make(ViewActions::class)->fire($clicked->uuid, $clicked);
        });

        $app['signals']->listen(GroupClicked::class, function (GroupClicked $clicked) use ($app): void {
            $app->make(ViewActions::class)->fire($clicked->uuid, $clicked);
        });

        // Return in a text input runs the handler ViewBuilder::input() bound to it.
        $app['signals']->listen(TextSubmitted::class, function (TextSubmitted $submitted) use ($app): void {
            $app->make(ViewActions::class)->fire($submitted->uuid, $submitted);
        });

        // Typing and choosing go to ViewActions::onChange() handlers only.
        $app['signals']->listen(TextChanged::class, function (TextChanged $changed) use ($app): void {
            $app->make(ViewActions::class)->fireChange($changed->uuid, $changed);
        });
        $app['signals']->listen(SelectionChanged::class, function (SelectionChanged $changed) use ($app): void {
            $app->make(ViewActions::class)->fireChange($changed->uuid, $changed);
        });
        $app['signals']->listen(ValueChanged::class, function (ValueChanged $changed) use ($app): void {
            $app->make(ViewActions::class)->fireChange($changed->uuid, $changed);
        });
        $app['signals']->listen(ViewScrolled::class, function (ViewScrolled $scrolled) use ($app): void {
            $app->make(ViewActions::class)->fireChange($scrolled->uuid, $scrolled);
        });
        $app['signals']->listen(ItemActivated::class, function (ItemActivated $activated) use ($app): void {
            $app->make(ViewActions::class)->fireChange($activated->uuid, $activated);
        });
        $app['signals']->listen(ItemActionClicked::class, function (ItemActionClicked $clicked) use ($app): void {
            $app->make(ViewActions::class)->fireChange($clicked->uuid, $clicked);
        });

    }
}
