<?php

namespace Surface\Contracts\Toolkits\UI;

use InvalidArgumentException;

/**
 * Space on each side, in points: a container's padding, a primitive's margin.
 *
 * Written as CSS writes it: one value is every side; two are top and bottom, then left and
 * right; three are top, left and right, then bottom; four go round from the top: top, right,
 * bottom, left.
 *
 *     Insets::of(16)            // 16 all round
 *     Insets::of('40 16')       // 40 top and bottom, 16 left and right
 *     Insets::of('40 16 8')     // 40 top, 16 left and right, 8 bottom
 *     Insets::of('40 16 8 0')   // top, right, bottom, left
 */
final readonly class Insets
{
    public function __construct(
        public int $top = 0,
        public int $right = 0,
        public int $bottom = 0,
        public int $left = 0,
    ) {
        if ($top < 0 || $right < 0 || $bottom < 0 || $left < 0) {
            throw new InvalidArgumentException("Insets are 0 or more on each side, got {$top} {$right} {$bottom} {$left}.");
        }
    }

    /**
     * @param int|string|list<int>|Insets $value one to four values, as CSS takes them
     * @throws InvalidArgumentException For anything else, or a negative value.
     */
    public static function of(int|string|array|Insets $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_int($value)) {
            return new self($value, $value, $value, $value);
        }

        if (is_string($value)) {
            $words = preg_split('/\s+/', trim($value), flags: PREG_SPLIT_NO_EMPTY);
            foreach ($words as $word) {
                if (! preg_match('/^-?\d+$/D', $word)) {
                    throw new InvalidArgumentException("'{$value}' is not one to four whole numbers, like '16' or '40 16'.");
                }
            }
            $value = array_map(intval(...), $words);
        }

        $sides = array_values($value);
        foreach ($sides as $side) {
            if (! is_int($side)) {
                throw new InvalidArgumentException('Insets are whole numbers.');
            }
        }

        return match (count($sides)) {
            1 => new self($sides[0], $sides[0], $sides[0], $sides[0]),
            2 => new self($sides[0], $sides[1], $sides[0], $sides[1]),
            3 => new self($sides[0], $sides[1], $sides[2], $sides[1]),
            4 => new self(...$sides),
            default => throw new InvalidArgumentException('Insets take one to four values, got '.count($sides).'.'),
        };
    }

    public static function none(): self
    {
        return new self();
    }

    public function isZero(): bool
    {
        return $this->top === 0 && $this->right === 0 && $this->bottom === 0 && $this->left === 0;
    }

    /**
     * The same on every side: a toolkit that takes one value can take this one.
     */
    public function isUniform(): bool
    {
        return $this->top === $this->right && $this->right === $this->bottom && $this->bottom === $this->left;
    }

    public function equals(self $other): bool
    {
        return $this == $other;
    }

    /**
     * In CSS order, the shortest way: '16', '40 16', '40 16 8' or '40 16 8 0'.
     */
    public function __toString(): string
    {
        return match (true) {
            $this->isUniform() => (string) $this->top,
            $this->left === $this->right && $this->top === $this->bottom => "{$this->top} {$this->right}",
            $this->left === $this->right => "{$this->top} {$this->right} {$this->bottom}",
            default => "{$this->top} {$this->right} {$this->bottom} {$this->left}",
        };
    }
}
