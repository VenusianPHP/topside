<?php

namespace Surface\Toolkits\Menus;

use Closure;
use Surface\Contracts\Toolkits\Menus\StandardKind;

/**
 * The About item, in the two forms it has. There is no About without choosing one, and none
 * at all unless a menu holds it.
 */
final class About
{
    private function __construct() {}

    /**
     * The toolkit's own About panel, showing these. Nothing reaches the application.
     */
    public static function panel(string $name, ?string $version = null, ?string $copyright = null, ?string $credits = null): StandardItem
    {
        return new StandardItem(StandardKind::ABOUT, panel: new AboutPanel($name, $version, $copyright, $credits));
    }

    /**
     * An About item the application answers: with an id it posts menu.clicked.{path}, with a
     * callback it runs that. One of the two is required. What opens is the application's own.
     *
     * @throws MenuException When it is given neither.
     */
    public static function item(?string $id = null, ?Closure $onClick = null): StandardItem
    {
        return new StandardItem(StandardKind::ABOUT, $id, onClick: $onClick);
    }
}
