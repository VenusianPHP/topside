<?php

namespace Surface\HumanInput\Devices;

/**
 * One key or button, as its device reports it: whether it is down, and how many times it went
 * down and up. The counts never clear: each reader keeps what it has seen of them (Seen), so a
 * button read by two stages gives each its press once.
 */
final class DigitalButton
{
    public bool $down = false;

    public int $presses = 0;

    public int $releases = 0;

    /** hrtime(true) of the last change; 0 before the first */
    public int $changedAt = 0;

    public function __construct(
        public readonly string $name,
    ) {}

    /** A report of the button's state. The same state again is not a change. */
    public function update(bool $down, ?int $at_ns = null): void
    {
        if ($down === $this->down) {
            return;
        }

        $this->down = $down;
        $down ? $this->presses++ : $this->releases++;
        $this->changedAt = $at_ns ?? hrtime(true);
    }
}
