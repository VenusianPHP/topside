<?php

namespace Surface\Contracts\Toolkits\Primitives;

use DateTimeImmutable;
use Surface\Contracts\Toolkits\Primitives\TKPrimitive;

/**
 * A calendar date. Posts DateChanged.
 */
interface TKDatepicker extends TKPrimitive
{
    /**
     * @return DateTimeImmutable|null
     */
    public function date(): ?DateTimeImmutable;

    /**
     * Posts nothing: the app changed it, it knows.
     * @param DateTimeImmutable|null $date
     * @return $this
     */
    public function setDate(?DateTimeImmutable $date): static;
}
