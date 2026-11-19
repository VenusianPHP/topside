<?php

namespace Surface\Contracts\Images;

/**
 * An encoded image Images can read, recognised from the leading bytes of the file. The name of the
 * file is not a format.
 */
enum ImageFormat: string
{
    case PNG = 'png';
    case JPEG = 'jpeg';
    case TIFF = 'tiff';

    /**
     * The format whose signature $bytes starts with. Null when the bytes are none of them, which
     * includes a signature cut short of its full length.
     */
    public static function sniff(string $bytes): ?self
    {
        return match (true) {
            str_starts_with($bytes, "\x89PNG\r\n\x1a\n") => self::PNG,
            str_starts_with($bytes, "\xff\xd8\xff") => self::JPEG,
            str_starts_with($bytes, "II*\0"), str_starts_with($bytes, "MM\0*") => self::TIFF,
            default => null,
        };
    }
}