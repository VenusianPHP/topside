<?php

namespace Surface\Toolkits\Support;

use Closure;

/**
 * Parts of the built views that follow app state: a bound label's text, a reveal's open state.
 * refresh() reads each one again and applies only what changed, so a view updates in place
 * (and animates) instead of being rebuilt.
 */
final class Live
{
    /** @var list<array{Closure, Closure, mixed}> read, apply, the last value read */
    private array $watchers = [];

    /**
     * Reads $read now and returns it; $apply gets each later value that differs.
     * @param Closure(): mixed      $read
     * @param Closure(mixed): void  $apply
     */
    public function watch(Closure $read, Closure $apply): mixed
    {
        $value = $read();
        $this->watchers[] = [$read, $apply, $value];

        return $value;
    }

    public function refresh(): void
    {
        foreach ($this->watchers as $i => [$read, $apply, $last]) {
            $value = $read();
            if ($value !== $last) {
                $this->watchers[$i][2] = $value;
                $apply($value);
            }
        }
    }
}
