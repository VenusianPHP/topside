<?php

namespace Surface\Contracts\Rendering\Rasterization;

/**
 * Optional: many ops scanned in one call. A scanner without this tier has its
 * batch answered op by op.
 */
interface BatchScanner extends Scanner
{
    /**
     * Many shapes in one call, answered as the calls of the same names answer them: one span string an op, in order.
     * An op is [kind, ...that method's arguments, every one given], except a path's rule, which is 0 for non-zero and
     * 1 for even-odd: [PATH, contours, 0|1], [ELLIPSE, cx, cy, rx, ry], [ROUND_RECT, x0, y0, x1, y1, radii]… A bad op
     * throws, and the batch answers nothing.
     *
     * @param  list<list<mixed>>  $ops
     * @return list<string>
     */
    public function batch(array $ops): array;
}
