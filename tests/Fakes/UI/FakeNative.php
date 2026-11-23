<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\NutsAndBolts\Color;
use Surface\Contracts\Toolkits\UI\Align;
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
        $this->record('applyVisible', $visible);
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
}
