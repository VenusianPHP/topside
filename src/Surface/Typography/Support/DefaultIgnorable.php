<?php

namespace Surface\Typography\Support;

/**
 * Unicode's default ignorable code points (DerivedCoreProperties, Default_Ignorable_Code_Point):
 * joiners, variation selectors, bidi controls and the like. They draw nothing and take no width,
 * and a face need not have them.
 */
final class DefaultIgnorable
{
    /** Inclusive ranges, ascending. */
    private const array RANGES = [
        [0x00AD, 0x00AD], [0x034F, 0x034F], [0x061C, 0x061C], [0x115F, 0x1160], [0x17B4, 0x17B5],
        [0x180B, 0x180F], [0x200B, 0x200F], [0x202A, 0x202E], [0x2060, 0x206F], [0x3164, 0x3164],
        [0xFE00, 0xFE0F], [0xFEFF, 0xFEFF], [0xFFA0, 0xFFA0], [0xFFF0, 0xFFF8], [0x1BCA0, 0x1BCA3],
        [0x1D173, 0x1D17A], [0xE0000, 0xE0FFF],
    ];

    public static function contains(int $codepoint): bool
    {
        if ($codepoint < 0x00AD) {
            return false;
        }
        foreach (self::RANGES as [$first, $last]) {
            if ($codepoint < $first) {
                return false;
            }
            if ($codepoint <= $last) {
                return true;
            }
        }

        return false;
    }
}
