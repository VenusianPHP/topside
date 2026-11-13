<?php

namespace Surface\Toolkits\Demo;

use Surface\Contracts\Toolkits\ViewBuilder;
use Surface\Contracts\Toolkits\Composition;

class DemoHomeView implements Composition
{
    public function name(): string
    {
        return 'home';
    }

    public function title(): string
    {
        return 'Welcome To VenusianPHP';
    }

    public function compose(ViewBuilder $ui, array $args): void
    {
        $toolkit = config('main.presentation.toolkit.driver', '???');

        $ui->spacer('above');
        $ui->label('name', 'Venusian Topside', 'display');
        $ui->label('tagline', 'PHP differently.', 'subtitle');
        $ui->spacer('gap');

        $cards = $ui->row('cards', 16);

        $native = $cards->card('native', 'Native windows');
        $native->label('body', "This window is drawn by {$toolkit}. The same code opens in AppKit, GTK and Qt.", 'body');

        $start = $cards->card('start', 'Make it yours');
        $start->label('body', 'Add routes/navigation.php to your application and give "/" your own page. It takes the place of this one.', 'body');

        $learn = $cards->card('learn', 'Learn more');
        $learn->label('body', 'Guides and the package reference are on the VenusianPHP site.', 'body');
        $learn->link('site', 'venusian.projectsaturnstudios.com', 'https://venusian.projectsaturnstudios.com');

        // A route name as the handler: the click goes to that route. See KitchenSinkView for every other widget.
        $actions = $ui->row('actions');
        $actions->spacer('left');
        $actions->button('kitchen-sink', 'Open the kitchen sink', '/kitchen-sink');
        $actions->spacer('right');

        $ui->spacer('below');

        $footer = $ui->row('footer');
        $footer->spacer('left');
        $footer->label('signature', 'Made with ADHD by ProjectSaturnStudios © 2026', 'footnote');
        $footer->spacer('right');
    }
}
