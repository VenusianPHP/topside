<?php

namespace Surface\Contracts\Rendering\Recording\Exceptions;

use RuntimeException;
use Surface\Contracts\Rendering\Recording\Enums\RecordingError;
use Throwable;

/**
 * A shape a recorder refused. $what is the argument or the method it names; $value what the number became.
 */
class CommandRecorderException extends RuntimeException
{
    public function __construct(
        public readonly RecordingError $error,
        public readonly string $what,
        public readonly float $value,
        ?Throwable $previous = null,
    ) {
        parent::__construct(match ($error) {
            RecordingError::NOT_RECORDING => "{$what}() needs an open frame: call begin() first.",
            RecordingError::NOT_FINITE => "{$what} is not a finite number.",
            RecordingError::PAST_LIMIT => sprintf('%s %g is past 16777216 once transformed.', $what, $value),
            RecordingError::BAD_RADII => 'Radii are a list of four numbers: [tl, tr, br, bl].',
            RecordingError::UNEVEN => "{$what}() needs an upright transform that scales x and y alike.",
            RecordingError::BAD_MATRIX => sprintf('The transform would have a component that is not finite: %g.', $value),
        }, $error->value, $previous);
    }

    public static function extensionMissing(): RuntimeException
    {
        return new RuntimeException("ext-comrec is not loaded. Install it with 'pie install php-io-extensions/comrec'.");
    }
}
