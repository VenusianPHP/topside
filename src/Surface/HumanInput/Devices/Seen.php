<?php

namespace Surface\HumanInput\Devices;

/**
 * What one reader has seen of some buttons' counts, as of its last settle. A press since then is
 * an edge for this reader, however many other readers have seen it.
 */
final class Seen
{
    /** @var array<string, array{int, int}> presses and releases by button name */
    private array $counts = [];

    public function pressed(DigitalButton $button): bool
    {
        return $button->presses > ($this->counts[$button->name][0] ?? 0);
    }

    public function released(DigitalButton $button): bool
    {
        return $button->releases > ($this->counts[$button->name][1] ?? 0);
    }

    /** @param iterable<DigitalButton> $buttons now seen as they are */
    public function mark(iterable $buttons): void
    {
        foreach ($buttons as $button) {
            $this->counts[$button->name] = [$button->presses, $button->releases];
        }
    }
}
