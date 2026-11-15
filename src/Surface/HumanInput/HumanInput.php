<?php

namespace Surface\HumanInput;

use ReflectionException;
use Surface\Contracts\Graphics\ActorStage;
use Surface\Contracts\HumanInput\HumanInputException;

/**
 * The way in to human input. Off unless config/human-input.php's enabled is true
 * (HUMAN_INPUT=true): a package that registers a backend or a pad source asks enabled() first.
 */
class HumanInput
{
    public static function enabled(): bool
    {
        return (bool) config('human-input.enabled', false);
    }

    /**
     * @throws HumanInputException When human input is off.
     * @throws ReflectionException
     */
    public static function manager(): HumanInputManager
    {
        if (! static::enabled()) {
            throw HumanInputException::disabled();
        }

        return app('human-input');
    }

    /**
     * @throws HumanInputException When human input is off, or the backend is not resolved yet.
     * @throws ReflectionException
     */
    public static function channel(ActorStage $stage): InputChannel
    {
        return static::manager()->channel($stage);
    }
}
