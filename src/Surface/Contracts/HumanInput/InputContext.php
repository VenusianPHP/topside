<?php

namespace Surface\Contracts\HumanInput;

use Stringable;

/**
 * What an application is presented with, as a backend is asked whether it serves it: the toolkit
 * its windows are made with, the graphics engine its canvases are drawn with, and the OS.
 */
final readonly class InputContext implements Stringable
{
    /**
     * @param string $toolkit the toolkit bridge's name: 'appkit', 'gtk', 'qt', 'raven'
     * @param string $engine the canvas graphics engine's name: 'velvet', 'metal', 'sdl3'
     * @param string $os 'mac' or 'linux', as device_os_family() answers
     */
    public function __construct(
        public string $toolkit,
        public string $engine,
        public string $os,
    ) {}

    public function __toString(): string
    {
        return "the '{$this->toolkit}' toolkit with '{$this->engine}' canvases on {$this->os}";
    }
}
