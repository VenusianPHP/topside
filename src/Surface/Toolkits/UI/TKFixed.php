<?php

namespace Surface\Toolkits\UI;

use Surface\Toolkits\ToolkitException;
use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\UI\TKFixed as PrimitiveContract;
use Surface\Contracts\Toolkits\UI\TKPrimitive as ChildContract;

/**
 * Children at pixel frames: at() sets the frame the next creation call takes.
 * The only container where pixels exist.
 *
 * A child's placement() is the frame it was created at and never changes; where it is now,
 * after every move() and resize(), is frameOf().
 */
abstract class TKFixed extends TKGroup implements PrimitiveContract
{
    /**
     * The frame at() set, until the next child takes it.
     */
    private ?Placement $pending = null;

    /**
     * Each child's frame now, by the child's uuid.
     *
     * @var array<string, Placement>
     */
    private array $frames = [];

    /** Frames place its children, so there is no order to move in. */
    protected function keepsOrder(): bool
    {
        return false;
    }

    /**
     * @throws ToolkitException When removed, or width or height is below 1.
     */
    public function at(int $x, int $y, int $width, int $height): static
    {
        $this->guardLive();
        $this->pending = self::frame($x, $y, $width, $height);

        return $this;
    }

    /**
     * @throws ToolkitException When removed, or the child is not here.
     */
    public function move(ChildContract $child, int $x, int $y): static
    {
        $this->guardLive();
        $frame = $this->frameOf($child);

        return $this->reframe($child, self::frame($x, $y, $frame->width, $frame->height));
    }

    /**
     * @throws ToolkitException When removed, the child is not here, or width or height is below 1.
     */
    public function resize(ChildContract $child, int $width, int $height): static
    {
        $this->guardLive();
        $frame = $this->frameOf($child);

        return $this->reframe($child, self::frame($frame->x, $frame->y, $width, $height));
    }

    /**
     * @throws ToolkitException When the child is not here.
     */
    public function frameOf(ChildContract $child): Placement
    {
        return $this->frames[$child->uuid()]
            ?? throw new ToolkitException("'{$child->path()}' is not in '{$this->path()}'.");
    }

    /**
     * The frame at() set, once. Without one there is nowhere to put the child.
     *
     * @throws ToolkitException When at() was not called for this child.
     */
    protected function nextPlacement(): Placement
    {
        $frame = $this->pending
            ?? throw new ToolkitException("'{$this->path()}' places its children by frame: call at() before creating one.");

        $this->pending = null;

        return $frame;
    }

    protected function adopt(TKPrimitive $child): void
    {
        $this->frames[$child->uuid()] = $child->placement();

        parent::adopt($child);
    }

    protected function release(TKPrimitive $child): void
    {
        unset($this->frames[$child->uuid()]);

        parent::release($child);
    }

    private function reframe(ChildContract $child, Placement $frame): static
    {
        $this->frames[$child->uuid()] = $frame;
        $this->applyFrame($child, $frame);

        return $this;
    }

    /**
     * Placement refuses a width or height below 1 with a RuntimeException of its own; said
     * here as every other refusal in the tree is.
     */
    private static function frame(int $x, int $y, int $width, int $height): Placement
    {
        if ($width < 1 || $height < 1) {
            throw new ToolkitException("A frame needs width and height >= 1, got {$width}x{$height}.");
        }

        return Placement::frame($x, $y, $width, $height);
    }

    /**
     * Put $child's native at $frame inside this one. The frame a child is created at reaches
     * the native in attachNative(), from $child->placement().
     */
    abstract protected function applyFrame(ChildContract $child, Placement $frame): void;
}
