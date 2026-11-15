<?php

namespace Surface\Rendering\Framebuffers;

use Surface\Contracts\Rendering\Framebuffers\FramebufferException;
use Surface\Contracts\Rendering\Framebuffers\InstanceThatManagesAFramebuffer;
use Surface\Contracts\Rendering\Framebuffers\Region;

/**
 * The base of every framebuffer module: one library's buffer behind InstanceThatManagesAFramebuffer.
 * It holds only what is the same whatever holds the pixels; each module fills in the rest from its
 * library, and implements the optional contracts its library really has.
 */
abstract class FramebufferDelegate implements InstanceThatManagesAFramebuffer
{
    /** Damage-tracking modules override this; every write path calls it once per region written. */
    protected function touched(Region $region): void {}

    /** Records $region as written (a null wrote nothing) and returns the framebuffer, for the write methods' return. */
    protected function wrote(?Region $region): static
    {
        if (! is_null($region)) {
            $this->touched($region);
        }

        return $this;
    }

    /** @throws FramebufferException When ($x, $y) is outside the surface. */
    protected function guard(int $x, int $y): void
    {
        if ($x < 0 || $y < 0 || $x >= $this->viewportWidth() || $y >= $this->viewportHeight()) {
            throw FramebufferException::outOfRange($x, $y, $this->viewportWidth(), $this->viewportHeight());
        }
    }

    /** The part of $clip whose pixels land inside $clip moved by ($dx, $dy); null when none do or none move. */
    public static function scrollSource(Region $clip, int $dx, int $dy): ?Region
    {
        if ($dx === 0 && $dy === 0) {
            return null;
        }

        return (new Region($clip->x - $dx, $clip->y - $dy, $clip->width, $clip->height))->intersect($clip);
    }

    /**
     * $bytes as flush() and flushRegion() answer them: the string, or its bytes as a list of ints.
     *
     * @return string|list<int>
     */
    public static function answer(string $bytes, bool $as_array): string|array
    {
        return $as_array ? array_values(unpack('C*', $bytes) ?: []) : $bytes;
    }
}
