<?php

namespace Surface\Contracts\Toolkits\UI;

use InvalidArgumentException;

/**
 * A radius for each corner, in points: how round a view's corners are.
 *
 * Written as CSS writes border-radius: one value is every corner; two are top-left and
 * bottom-right, then top-right and bottom-left; three are top-left, then top-right and
 * bottom-left, then bottom-right; four go round from the top-left: top-left, top-right,
 * bottom-right, bottom-left.
 *
 *     Corners::of(12)             // 12 on every corner
 *     Corners::of('0 0 14 14')    // square on top, 14 at the bottom
 */
final readonly class Corners
{
    public function __construct(
        public float $topLeft = 0.0,
        public float $topRight = 0.0,
        public float $bottomRight = 0.0,
        public float $bottomLeft = 0.0,
    ) {
        if ($topLeft < 0 || $topRight < 0 || $bottomRight < 0 || $bottomLeft < 0) {
            throw new InvalidArgumentException("A corner radius is 0 or more, got {$topLeft} {$topRight} {$bottomRight} {$bottomLeft}.");
        }
    }

    /**
     * @param int|float|string|list<int|float>|Corners $value one to four radii, as CSS takes them
     * @throws InvalidArgumentException For anything else, or a negative radius.
     */
    public static function of(int|float|string|array|Corners $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return new self((float) $value, (float) $value, (float) $value, (float) $value);
        }

        if (is_string($value)) {
            $words = preg_split('/\s+/', trim($value), flags: PREG_SPLIT_NO_EMPTY);
            if ($words === []) {
                throw new InvalidArgumentException("'{$value}' is not one to four radii, like '12' or '0 0 14 14'.");
            }
            foreach ($words as $word) {
                if (! is_numeric($word)) {
                    throw new InvalidArgumentException("'{$value}' is not one to four radii, like '12' or '0 0 14 14'.");
                }
            }
            $value = array_map(floatval(...), $words);
        }

        $radii = [];
        foreach (array_values($value) as $radius) {
            if (! is_int($radius) && ! is_float($radius)) {
                throw new InvalidArgumentException('Corner radii are numbers.');
            }
            $radii[] = (float) $radius;
        }

        return match (count($radii)) {
            1 => new self($radii[0], $radii[0], $radii[0], $radii[0]),
            2 => new self($radii[0], $radii[1], $radii[0], $radii[1]),
            3 => new self($radii[0], $radii[1], $radii[2], $radii[1]),
            4 => new self(...$radii),
            default => throw new InvalidArgumentException('Corners take one to four radii, got '.count($radii).'.'),
        };
    }

    public static function none(): self
    {
        return new self();
    }

    public function isZero(): bool
    {
        return $this->largest() === 0.0;
    }

    /**
     * The same on every corner: a toolkit that takes one radius can take this one.
     */
    public function isUniform(): bool
    {
        return $this->topLeft === $this->topRight
            && $this->topRight === $this->bottomRight
            && $this->bottomRight === $this->bottomLeft;
    }

    /**
     * The largest of the four: what a toolkit with one radius and a choice of corners rounds with.
     */
    public function largest(): float
    {
        return max($this->topLeft, $this->topRight, $this->bottomRight, $this->bottomLeft);
    }

    /**
     * @return array{float, float, float, float} top-left, top-right, bottom-right, bottom-left
     */
    public function toArray(): array
    {
        return [$this->topLeft, $this->topRight, $this->bottomRight, $this->bottomLeft];
    }

    public function equals(self $other): bool
    {
        return $this == $other;
    }

    /**
     * In CSS order, the shortest way: '12', '12 0', '12 0 4' or '0 0 14 14'.
     */
    public function __toString(): string
    {
        $f = static fn (float $r): string => rtrim(rtrim(number_format($r, 2, '.', ''), '0'), '.');

        return match (true) {
            $this->isUniform() => $f($this->topLeft),
            $this->topLeft === $this->bottomRight && $this->topRight === $this->bottomLeft => "{$f($this->topLeft)} {$f($this->topRight)}",
            $this->topRight === $this->bottomLeft => "{$f($this->topLeft)} {$f($this->topRight)} {$f($this->bottomRight)}",
            default => "{$f($this->topLeft)} {$f($this->topRight)} {$f($this->bottomRight)} {$f($this->bottomLeft)}",
        };
    }
}
