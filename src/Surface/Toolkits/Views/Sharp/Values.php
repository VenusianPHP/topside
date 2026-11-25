<?php

namespace Surface\Toolkits\Views\Sharp;

use UnitEnum;
use BackedEnum;
use Throwable;
use DateTimeImmutable;
use ReflectionType;
use ReflectionNamedType;
use ReflectionUnionType;
use InvalidArgumentException;
use Surface\NutsAndBolts\Color;
use Surface\Contracts\Toolkits\UI\Insets;
use Surface\Contracts\Toolkits\UI\Corners;
use Surface\Toolkits\Menus\Shortcut;
use Surface\Toolkits\Menus\MenuException;
use Surface\Contracts\Toolkits\Styling\FontSpec;
use Surface\Contracts\Toolkits\Styling\FontWeight;

/**
 * An attribute's text as the PHP value its type takes: spacing="12" is the integer 12,
 * align="center" is Align::CENTER, background="#80808024" a colour, font="15 bold" a font,
 * padding="40 16" insets (one to four values, as CSS writes them).
 *
 * @internal
 */
final class Values
{
    /**
     * @param string|null $text null when the attribute's name stands alone, which is true
     * @return string PHP code for the value
     * @throws InvalidArgumentException When the type does not take $text.
     */
    public static function code(?ReflectionType $type, ?string $text): string
    {
        $names = self::names($type);
        $any = $names === [] || in_array('mixed', $names, true);

        if (is_null($text)) {
            return $any || in_array('bool', $names, true) ? 'true' : throw new InvalidArgumentException('it needs a value');
        }
        if (in_array('bool', $names, true) && in_array($text, ['true', 'false'], true)) {
            return $text;
        }
        if (in_array('int', $names, true) && preg_match('/^-?\d+$/D', $text)) {
            return var_export((int) $text, true);
        }
        if (in_array('float', $names, true) && is_numeric($text)) {
            return var_export((float) $text, true);
        }

        foreach ($names as $name) {
            if (enum_exists($name) && ! is_null($case = self::named($name, $text))) {
                return "\\{$name}::{$case->name}";
            }
            if ($name === Color::class) {
                return self::color($text);
            }
            if ($name === DateTimeImmutable::class) {
                return self::day($text);
            }
            if ($name === FontSpec::class) {
                return self::font($text);
            }
            if ($name === Shortcut::class) {
                return self::shortcut($text);
            }
            if ($name === Insets::class) {
                return self::insets($text);
            }
            if ($name === Corners::class) {
                return self::corners($text);
            }
        }

        if ($any || in_array('string', $names, true)) {
            return var_export($text, true);
        }

        foreach ($names as $name) {
            if (enum_exists($name)) {
                $cases = implode(', ', array_map(static fn (UnitEnum $case): string => strtolower($case->name), $name::cases()));
                throw new InvalidArgumentException("'{$text}' is none of {$cases}");
            }
        }

        throw new InvalidArgumentException("'{$text}' is not ".implode('|', $names).' (for PHP, put a colon before the name)');
    }

    public static function takesString(?ReflectionType $type): bool
    {
        $names = self::names($type);

        return $names === [] || array_intersect($names, ['string', 'mixed']) !== [];
    }

    private static function named(string $enum, string $text): ?UnitEnum
    {
        foreach ($enum::cases() as $case) {
            if (strcasecmp($case->name, $text) === 0 || ($case instanceof BackedEnum && (string) $case->value === $text)) {
                return $case;
            }
        }

        return null;
    }

    private static function color(string $text): string
    {
        try {
            Color::hex($text);
        } catch (Throwable $e) {
            throw new InvalidArgumentException("'{$text}' is not a colour like #336699 or #33669980", previous: $e);
        }

        return '\\'.Color::class.'::hex('.var_export($text, true).')';
    }

    private static function shortcut(string $text): string
    {
        try {
            Shortcut::of($text);
        } catch (MenuException $e) {
            throw new InvalidArgumentException($e->getMessage(), previous: $e);
        }

        return '\\'.Shortcut::class.'::of('.var_export($text, true).')';
    }

    private static function insets(string $text): string
    {
        try {
            Insets::of($text);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException("'{$text}' is not one to four sizes like '16', '40 16', '40 16 8' or '40 16 8 0'", previous: $e);
        }

        return '\\'.Insets::class.'::of('.var_export($text, true).')';
    }

    private static function corners(string $text): string
    {
        try {
            Corners::of($text);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException("'{$text}' is not one to four radii like '12' or '0 0 14 14'", previous: $e);
        }

        return '\\'.Corners::class.'::of('.var_export($text, true).')';
    }

    private static function day(string $text): string
    {
        if (! preg_match('/^(\d{4})-(\d\d)-(\d\d)$/D', $text, $found) || ! checkdate((int) $found[2], (int) $found[3], (int) $found[1])) {
            throw new InvalidArgumentException("'{$text}' is not a day like 2026-11-21");
        }

        return 'new \\'.DateTimeImmutable::class.'('.var_export($text, true).')';
    }

    /**
     * A size, then in any order a weight, italic and monospace.
     */
    private static function font(string $text): string
    {
        $words = preg_split('/\s+/', trim($text));
        $size = array_shift($words);
        if (! is_numeric($size) || $size <= 0) {
            throw new InvalidArgumentException("'{$text}' is not a font like '15 bold italic'");
        }

        $with = [var_export((float) $size, true)];
        foreach ($words as $word) {
            $weight = FontWeight::tryFrom($word);
            $with[] = match (true) {
                ! is_null($weight) => 'weight: \\'.FontWeight::class."::{$weight->name}",
                $word === 'italic', $word === 'monospace' => "{$word}: true",
                default => throw new InvalidArgumentException("'{$word}' is neither a weight, italic nor monospace"),
            };
        }
        if (count(array_unique(array_map(static fn (string $w): string => strtok($w, ':'), $with))) !== count($with)) {
            throw new InvalidArgumentException("'{$text}' says one thing twice");
        }

        return 'new \\'.FontSpec::class.'('.implode(', ', $with).')';
    }

    /**
     * @return list<string>
     */
    private static function names(?ReflectionType $type): array
    {
        return match (true) {
            $type instanceof ReflectionNamedType => [$type->getName()],
            $type instanceof ReflectionUnionType => array_merge(...array_map(self::names(...), $type->getTypes())),
            default => [],
        };
    }
}
