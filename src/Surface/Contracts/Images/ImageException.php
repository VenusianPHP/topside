<?php

namespace Surface\Contracts\Images;

use Surface\Contracts\Core\TopsideException;
use Surface\Contracts\Rendering\deprecated\Framebuffers\FormatSpec;

/**
 * What Images throws. The message names the format, the frame, the size, or the target layout that
 * failed, so a caller can tell two failures apart.
 */
class ImageException extends TopsideException
{
    /** The bytes are none of the formats Images reads. */
    public static function unknownFormat(): self
    {
        return new self('The bytes are not a PNG, JPEG or TIFF image.');
    }

    /** The file is that format and a decoder refused it, for $reason. */
    public static function corrupt(ImageFormat $format, string $reason): self
    {
        return new self(strtoupper($format->value)." image could not be read: {$reason}");
    }

    /** Width or height, or the two together, past the limit Images was given. */
    public static function tooLarge(ImageFormat $format, int $width, int $height): self
    {
        return new self(strtoupper($format->value)." image is {$width}x{$height}, past the limit.");
    }

    /** The format is one Images knows, and no registered decoder reads it. */
    public static function unread(ImageFormat $format): self
    {
        return new self(strtoupper($format->value).' has no decoder.');
    }

    /** No registered decoder both reads $format and writes $target. */
    public static function noDecoder(ImageFormat $format, FormatSpec $target): self
    {
        $layout = $target->channel_order?->name ?? $target->pixel_format->value;

        return new self(strtoupper($format->value)." has no decoder that writes {$layout}.");
    }

    /** $driver writes an address, and this frame lends none. */
    public static function addressOnly(string $driver, ImageFormat $format): self
    {
        return new self("{$driver} writes an address, and this ".strtoupper($format->value).' frame has none.');
    }

    /** $frame is not among the $frames the file holds. Frames are numbered from 0. */
    public static function frameOutOfRange(ImageFormat $format, int $frame, int $frames): self
    {
        return new self(strtoupper($format->value)." image has no frame {$frame}; it has {$frames}.");
    }

    /** A path source names no file that can be read. An empty path names nothing. */
    public static function unreadable(string $path): self
    {
        if ($path === '') {
            return new self('An image source names a file.');
        }

        return new self("Image file '{$path}' could not be read.");
    }
}