<?php

namespace Surface\Contracts\Toolkits\Menus;

/**
 * The keys a menu shortcut can carry on every toolkit. The value is how the key is written
 * in Shortcut::of(): 'S', ',', 'F5', 'PageUp'.
 */
enum ShortcutKey: string
{
    case A = 'A'; case B = 'B'; case C = 'C'; case D = 'D'; case E = 'E'; case F = 'F'; case G = 'G';
    case H = 'H'; case I = 'I'; case J = 'J'; case K = 'K'; case L = 'L'; case M = 'M'; case N = 'N';
    case O = 'O'; case P = 'P'; case Q = 'Q'; case R = 'R'; case S = 'S'; case T = 'T'; case U = 'U';
    case V = 'V'; case W = 'W'; case X = 'X'; case Y = 'Y'; case Z = 'Z';

    case DIGIT_0 = '0'; case DIGIT_1 = '1'; case DIGIT_2 = '2'; case DIGIT_3 = '3'; case DIGIT_4 = '4';
    case DIGIT_5 = '5'; case DIGIT_6 = '6'; case DIGIT_7 = '7'; case DIGIT_8 = '8'; case DIGIT_9 = '9';

    case MINUS = '-'; case EQUALS = '='; case LEFT_BRACKET = '['; case RIGHT_BRACKET = ']';
    case BACKSLASH = '\\'; case SEMICOLON = ';'; case APOSTROPHE = "'"; case GRAVE = '`';
    case COMMA = ','; case PERIOD = '.'; case SLASH = '/';

    case F1 = 'F1'; case F2 = 'F2'; case F3 = 'F3'; case F4 = 'F4'; case F5 = 'F5'; case F6 = 'F6';
    case F7 = 'F7'; case F8 = 'F8'; case F9 = 'F9'; case F10 = 'F10'; case F11 = 'F11'; case F12 = 'F12';

    case UP = 'Up'; case DOWN = 'Down'; case LEFT = 'Left'; case RIGHT = 'Right';
    case HOME = 'Home'; case END = 'End'; case PAGE_UP = 'PageUp'; case PAGE_DOWN = 'PageDown';
    case SPACE = 'Space'; case ENTER = 'Enter'; case TAB = 'Tab'; case ESCAPE = 'Escape';
    case BACKSPACE = 'Backspace'; case DELETE = 'Delete';

    /**
     * The key written as $name, in any case: 's', 'S', 'pageup', 'F5'. Null when no key is.
     */
    public static function named(string $name): ?self
    {
        static $byName = null;
        $byName ??= array_column(
            array_map(static fn (self $key): array => [strtolower($key->value), $key], self::cases()),
            1,
            0,
        );

        return $byName[strtolower($name)] ?? null;
    }

    /**
     * F1 to F12.
     */
    public function isFunctionKey(): bool
    {
        return preg_match('/^F\d+$/', $this->value) === 1;
    }

    /**
     * Whether a shortcut may be this key with no Primary, Ctrl or Alt: it types nothing, and
     * belongs neither to focus nor to dialogs.
     */
    public function standsAlone(): bool
    {
        return $this->isFunctionKey() || in_array($this, [
            self::DELETE, self::BACKSPACE,
            self::UP, self::DOWN, self::LEFT, self::RIGHT,
            self::HOME, self::END, self::PAGE_UP, self::PAGE_DOWN,
        ], true);
    }
}
