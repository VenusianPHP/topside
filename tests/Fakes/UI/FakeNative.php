<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\NutsAndBolts\Color;
use Surface\Contracts\Toolkits\UI\Align;
use Surface\Contracts\Toolkits\UI\Cursor;
use Surface\Contracts\Toolkits\UI\Insets;
use Surface\Contracts\Toolkits\UI\TKTransition;

/**
 * What every fake primitive does: its native is a plain object, and each hook the TK class
 * calls is recorded in order, by name with its arguments.
 */
trait FakeNative
{
    /** Primitives built in this process, for "nothing was rebuilt" checks. */
    public static int $built = 0;

    /** @var list<array{string, list<mixed>}> hook name, arguments */
    public array $calls = [];

    protected ?object $native = null;

    public function native(): object
    {
        return $this->native;
    }

    /**
     * The hooks this primitive was told, by name only.
     *
     * @return list<string>
     */
    public function hooks(): array
    {
        return array_map(static fn (array $call): string => $call[0], $this->calls);
    }

    public function hasFocus(): bool
    {
        return false;
    }

    public function size(): array
    {
        return [0, 0];
    }

    protected function record(string $hook, mixed ...$args): void
    {
        $this->calls[] = [$hook, $args];
    }

    protected function bootstrapNative(): void
    {
        self::$built++;
        $this->native = (object) ['uuid' => $this->uuid()];
        $this->record('bootstrapNative');
    }

    protected function releaseNative(): void
    {
        $this->record('releaseNative');
        $this->native = null;
    }

    protected function applyVisible(bool $visible, ?TKTransition $transition, float $seconds): void
    {
        $this->record('applyVisible', $visible, $transition, $seconds);
    }

    protected function applyEnabled(bool $enabled): void
    {
        $this->record('applyEnabled', $enabled);
    }

    protected function applyOpacity(float $opacity): void
    {
        $this->record('applyOpacity', $opacity);
    }

    protected function nativeFocus(): bool
    {
        return true;
    }

    protected function applyBackground(?Color $color): void
    {
        $this->record('applyBackground', $color);
    }

    protected function applyCornerRadius(float $radius): void
    {
        $this->record('applyCornerRadius', $radius);
    }

    protected function applyFill(bool $horizontal, bool $vertical): void
    {
        $this->record('applyFill', $horizontal, $vertical);
    }

    protected function applyAlign(Align $horizontal, Align $vertical): void
    {
        $this->record('applyAlign', $horizontal, $vertical);
    }

    protected function applyMinSize(int $width, int $height): void
    {
        $this->record('applyMinSize', $width, $height);
    }

    protected function applyMaxSize(int $width, int $height): void
    {
        $this->record('applyMaxSize', $width, $height);
    }

    protected function applyMargin(Insets $margin): void
    {
        $this->record('applyMargin', $margin);
    }

    protected function applyCursor(?Cursor $cursor): void
    {
        $this->record('applyCursor', $cursor);
    }

    protected function applyTooltip(?string $tooltip): void
    {
        $this->record('applyTooltip', $tooltip);
    }

    /**
     * The user clicks the native group at $x, $y: the toolkit's click handling calls
     * groupClicked(). Only a group has it.
     */
    public function clickAt(float $x = 0.0, float $y = 0.0): void
    {
        $this->groupClicked($x, $y);
    }

    protected function applyClickable(bool $on): void
    {
        $this->record('applyClickable', $on);
    }

    protected function applyClip(bool $clip): void
    {
        $this->record('applyClip', $clip);
    }

    protected function applyBorder(int $width, ?Color $color): void
    {
        $this->record('applyBorder', $width, $color);
    }
}
