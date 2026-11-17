<?php

namespace Surface\Contracts\Typography;

/** A face's vertical metrics at a size, in drawing units: ascent above the baseline, descent below it. */
final readonly class FontMetrics
{
    public function __construct(
        public float $ascent,
        public float $descent,
        public float $lineGap,
    ) {}

    /** From one baseline to the next. */
    public function lineHeight(): float
    {
        return $this->ascent + $this->descent + $this->lineGap;
    }
}
