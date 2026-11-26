<?php

namespace Surface\Toolkits\Menus;

use Closure;
use Surface\Contracts\Toolkits\Menus\StandardKind;

/**
 * The items a platform knows, as values to put in a menu. Each comes with the platform's
 * label and shortcut. None is in any bar unless the bar's definition holds it.
 */
final class Standard
{
    private function __construct() {}

    /**
     * Posts QuitRequested, as closing the application's window does.
     */
    public static function quit(): StandardItem
    {
        return new StandardItem(StandardKind::QUIT);
    }

    /**
     * Posts menu.clicked.{path} with an id, or runs a callback. One of the two is required.
     *
     * @throws MenuException When it is given neither.
     */
    public static function preferences(?string $id = null, ?Closure $onClick = null): StandardItem
    {
        return new StandardItem(StandardKind::PREFERENCES, $id, onClick: $onClick);
    }

    /** macOS only. */
    public static function hide(): StandardItem
    {
        return new StandardItem(StandardKind::HIDE);
    }

    /** macOS only. */
    public static function hideOthers(): StandardItem
    {
        return new StandardItem(StandardKind::HIDE_OTHERS);
    }

    /** macOS only. */
    public static function showAll(): StandardItem
    {
        return new StandardItem(StandardKind::SHOW_ALL);
    }

    public static function minimize(): StandardItem
    {
        return new StandardItem(StandardKind::MINIMIZE);
    }

    public static function closeWindow(): StandardItem
    {
        return new StandardItem(StandardKind::CLOSE_WINDOW);
    }

    public static function fullscreen(): StandardItem
    {
        return new StandardItem(StandardKind::FULLSCREEN);
    }

    public static function undo(): StandardItem
    {
        return new StandardItem(StandardKind::UNDO);
    }

    public static function redo(): StandardItem
    {
        return new StandardItem(StandardKind::REDO);
    }

    public static function cut(): StandardItem
    {
        return new StandardItem(StandardKind::CUT);
    }

    public static function copy(): StandardItem
    {
        return new StandardItem(StandardKind::COPY);
    }

    public static function paste(): StandardItem
    {
        return new StandardItem(StandardKind::PASTE);
    }

    /**
     * Pastes the clipboard's text in the style of the text around it. Where a text widget only
     * holds plain text, that is its paste.
     */
    public static function pasteAndMatchStyle(): StandardItem
    {
        return new StandardItem(StandardKind::PASTE_AND_MATCH_STYLE);
    }

    public static function selectAll(): StandardItem
    {
        return new StandardItem(StandardKind::SELECT_ALL);
    }

    /**
     * An Edit menu, id 'edit'. A Menu like any other: append to it, or build another.
     */
    public static function editMenu(): Menu
    {
        return Menu::make(
            'Edit',
            'edit',
            self::undo(),
            self::redo(),
            Separator::make(),
            self::cut(),
            self::copy(),
            self::paste(),
            self::selectAll(),
        );
    }

    /**
     * A Window menu, id 'window': minimize, fullscreen, close window.
     */
    public static function windowMenu(): Menu
    {
        return Menu::make('Window', 'window', self::minimize(), self::fullscreen(), self::closeWindow());
    }
}
