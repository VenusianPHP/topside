<?php

namespace Surface\Toolkits\Views\Sharp;

use Throwable;
use Surface\Toolkits\Views\ViewException;

/**
 * A sharp template that cannot be compiled as written, with the line to look at.
 */
class SharpCompileError extends ViewException
{
    public function __construct(
        public readonly string $problem,
        public readonly string $template,
        public readonly int $templateLine,
        ?Throwable $previous = null,
    ) {
        parent::__construct("{$problem} ({$template}:{$templateLine})", 0, $previous);
    }
}
