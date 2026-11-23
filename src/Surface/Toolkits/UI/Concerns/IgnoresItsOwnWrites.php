<?php

namespace Surface\Toolkits\UI\Concerns;

use Closure;

/**
 * A toolkit signals the changes topside writes as well as the user's, some of them in steps
 * (cleared, then set). While topside writes, what the native signals is not the user's.
 */
trait IgnoresItsOwnWrites
{
    private bool $writing = false;

    protected function write(Closure $write): void
    {
        $was = $this->writing;
        $this->writing = true;
        try {
            $write();
        } finally {
            $this->writing = $was;
        }
    }

    protected function isWriting(): bool
    {
        return $this->writing;
    }

    /**
     * Whether what the native signals is the user's, to a primitive that takes it.
     */
    protected function hearsUser(): bool
    {
        return ! $this->writing && ! $this->isRemoved() && $this->takesInput();
    }
}
