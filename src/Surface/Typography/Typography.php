<?php

namespace Surface\Typography;

use Surface\Contracts\Typography\Font;
use Surface\Contracts\Typography\TextAlign;
use Surface\Contracts\Typography\TypesetText;

/** Typography's face: Typography::font('body'), Typography::set($text, $font, $width). */
final class Typography
{
    /** The named font from config/typography.php, or the default one. */
    public static function font(?string $name = null): Font
    {
        return static::fonts()->font($name);
    }

    /** The text laid out in lines: measure it, then draw it. */
    public static function set(string $text, Font $font, ?float $width = null, TextAlign $align = TextAlign::LEFT): TypesetText
    {
        return static::typesetter()->set($text, $font, $width, $align);
    }

    public static function fonts(): FontRegistry
    {
        return app('typography');
    }

    public static function typesetter(): Typesetter
    {
        return app('typesetter');
    }
}
