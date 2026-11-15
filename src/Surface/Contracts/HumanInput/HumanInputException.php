<?php

namespace Surface\Contracts\HumanInput;

use Surface\Contracts\Core\TopsideException;

/**
 * Input failures topside can name. A backend or pad source that cannot run here throws one from
 * its creator or constructor: that is how it refuses, and the refusal is carried into the
 * message the application sees.
 */
class HumanInputException extends TopsideException
{
    public static function disabled(): static
    {
        return new static("Human input is off: set config/human-input.php's enabled (HUMAN_INPUT=true).");
    }

    public static function noBackend(InputContext $context): static
    {
        return new static("Human input is on but human-input.backend names no backend, so {$context} cannot have input.");
    }

    /** @param list<string> $registered */
    public static function unknownBackend(string $name, array $registered): static
    {
        return new static("No input backend named '{$name}' is registered (registered: ".self::names($registered).'): install the package that provides it.');
    }

    public static function refused(string $name, InputContext $context, string $reason): static
    {
        return new static("The '{$name}' input backend cannot run for {$context}: {$reason}");
    }

    public static function doesNotServe(string $name, InputContext $context): static
    {
        return new static("The '{$name}' input backend does not serve {$context}.");
    }

    /** @param list<string> $registered */
    public static function unknownPadSource(string $name, array $registered): static
    {
        return new static("No pad source named '{$name}' is registered (registered: ".self::names($registered).'): install the package that provides it, or set human-input.pads to null.');
    }

    public static function padSourceRefused(string $name, string $reason): static
    {
        return new static("The '{$name}' pad source cannot run here: {$reason}");
    }

    public static function unsupportedButton(string $device, GamepadButton $button): static
    {
        return new static("'{$device}' has no {$button->value} button.");
    }

    public static function notResolved(): static
    {
        return new static("Human input has no backend yet: the application resolves it as it boots (LoadHumanInputBackend).");
    }

    public static function channelClosed(): static
    {
        return new static('This input channel was closed: open another for the stage.');
    }

    /** @param list<string> $names */
    private static function names(array $names): string
    {
        return $names === [] ? 'none' : implode(', ', $names);
    }
}
