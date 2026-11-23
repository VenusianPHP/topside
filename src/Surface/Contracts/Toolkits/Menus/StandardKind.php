<?php

namespace Surface\Contracts\Toolkits\Menus;

/**
 * The items a platform knows by itself: where it has a usual label and shortcut for them, and
 * for most of them does the work.
 */
enum StandardKind: string
{
    case ABOUT = 'about';
    case PREFERENCES = 'preferences';
    case QUIT = 'quit';

    case HIDE = 'hide';
    case HIDE_OTHERS = 'hide-others';
    case SHOW_ALL = 'show-all';

    case MINIMIZE = 'minimize';
    case CLOSE_WINDOW = 'close-window';
    case FULLSCREEN = 'fullscreen';

    case UNDO = 'undo';
    case REDO = 'redo';
    case CUT = 'cut';
    case COPY = 'copy';
    case PASTE = 'paste';
    case SELECT_ALL = 'select-all';

    /**
     * Acts on the focused text widget, inside the toolkit; posts nothing.
     */
    public function isEdit(): bool
    {
        return in_array($this, [self::UNDO, self::REDO, self::CUT, self::COPY, self::PASTE, self::SELECT_ALL], true);
    }

    /**
     * Acts on the focused window, inside the toolkit.
     */
    public function isWindow(): bool
    {
        return in_array($this, [self::MINIMIZE, self::CLOSE_WINDOW, self::FULLSCREEN], true);
    }

    /**
     * Only macOS has it; left out of the bar everywhere else.
     */
    public function isMacOnly(): bool
    {
        return in_array($this, [self::HIDE, self::HIDE_OTHERS, self::SHOW_ALL], true);
    }

    /**
     * The toolkit does the work with nothing from the application: every kind but About,
     * Preferences and Quit.
     */
    public function isNative(): bool
    {
        return $this->isEdit() || $this->isWindow() || $this->isMacOnly();
    }
}
