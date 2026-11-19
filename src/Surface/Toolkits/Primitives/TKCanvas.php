<?php

namespace Surface\Toolkits\Primitives;

use ReflectionException;
use Surface\Contracts\HumanInput\HumanInputException;
use Surface\Contracts\Toolkits\Primitives\TKCanvas as PrimitiveContract;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;
use Surface\HumanInput\HumanInput;
use Surface\HumanInput\InputChannel;
use Surface\Contracts\Rendering\PixelMemory;
use Surface\Contracts\Rendering\Region;

/**
 * A region of a toolkit window that shows a graphics engine's frames: the engine's memory, read where it lies by the
 * toolkit's own image calls. It has no size of its own: the layout sizes it, so give it fill() or a minSize().
 */
abstract class TKCanvas extends TKPrimitive implements PrimitiveContract
{
    private ?InputChannel $input = null;

    public function pixelSize(): array
    {
        $this->live();
        [$width, $height] = $this->nativeSize();
        $scale = $this->nativeScale();

        return [(int) round($width * $scale), (int) round($height * $scale)];
    }

    public function pixelScale(): float
    {
        $this->live();

        return $this->nativeScale();
    }

    public function present(PixelMemory $memory, Region ...$changed): void
    {
        $this->live();
        $this->applyMemory($memory, ...$changed);
    }

    /**
     * The keyboard, pointer and pads this canvas reads, from the application's input backend.
     *
     * @throws HumanInputException When human input is off (config/human-input.php enabled).
     * @throws ReflectionException
     */
    public function input(): InputChannel
    {
        $this->live();
        if (is_null($this->input) || $this->input->closed()) {
            $this->input = HumanInput::channel($this);
        }

        return $this->input;
    }

    /** The input this canvas opened, while it is open: a toolkit's canvas takes the keyboard only then. */
    protected function openInput(): ?InputChannel
    {
        return is_null($this->input) || $this->input->closed() ? null : $this->input;
    }

    public function settleInput(): void
    {
        if (! is_null($this->input) && ! $this->input->closed()) {
            $this->input->settle();
        }
    }

    /** Terminal, as every primitive's: its input closes with it. */
    public function remove(): void
    {
        $this->input?->close();
        $this->input = null;
        parent::remove();
    }

    /** Device pixels to one unit of the toolkit's layout, where this canvas is shown. */
    abstract protected function nativeScale(): float;

    /**
     * The memory as what the native shows, stretched over its bounds: read in place, in the memory's own channel order
     * and alpha model. $changed is where it differs from what the native showed last; a toolkit that replaces its
     * whole image may show all of it.
     *
     * @throws ToolkitRuntimeException When the toolkit cannot make an image of the memory.
     */
    abstract protected function applyMemory(PixelMemory $memory, Region ...$changed): void;
}
