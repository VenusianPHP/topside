<?php

namespace Venusian\Topside\Tests\Fakes;

use RuntimeException;
use Surface\Contracts\Toolkits\UI\TKColumn as ColumnContract;
use Surface\Toolkits\ToolkitWindow;

/**
 * A window whose "native" is a plain object. It records each lifecycle step, and its
 * user*() and native*() methods stand in for the toolkit's signals.
 */
class FakeWindow extends ToolkitWindow
{
    /** @var list<string> lifecycle steps in the order they ran */
    public array $steps = [];

    public int $settles = 0;

    public int $presents = 0;

    public string $title = '';

    /** @var array{int, int} */
    public array $content_size = [0, 0];

    /** Makes the next closeNative() fail, as a toolkit error would. */
    public bool $fail_close = false;

    protected ?object $window = null;

    public function present(): static
    {
        $this->live();
        $this->presents++;

        return $this;
    }

    public function setTitle(string $title): static
    {
        $this->live();
        $this->title = $title;

        return $this;
    }

    public function native(): object
    {
        return $this->live();
    }

    public function size(): array
    {
        $this->live();

        return $this->content_size;
    }

    public function isOpen(): bool
    {
        return ! is_null($this->window);
    }

    public function settle(): void
    {
        $this->settles++;
    }

    /** Mount a content tree, as the content API will. */
    public function adoptContent(ColumnContract $group): void
    {
        $this->content_group = $group;
    }

    /** The window system's close button: true when the window closed. */
    public function userClose(): bool
    {
        if (! $this->allowsUserClose()) {
            return false;
        }
        $this->closed();

        return true;
    }

    /** The toolkit reports a new content-area size. */
    public function nativeResized(int $width, int $height): void
    {
        $this->content_size = [$width, $height];
        $this->resized($width, $height);
    }

    /** The window system made it the focused window. */
    public function nativeFocused(): void
    {
        $this->gainedFocus();
    }

    /** The window system took the focus off it. */
    public function nativeUnfocused(): void
    {
        $this->lostFocus();
    }

    /** The toolkit destroyed the window on its own: the native signal reaches the close path. */
    public function nativeDestroyed(): void
    {
        $this->closed();
    }

    protected function bootstrapWindow(int $width, int $height): void
    {
        $this->steps[] = 'bootstrap';
        $this->window = (object) ['width' => $width, 'height' => $height];
        $this->content_size = [$width, $height];
        $this->title = $this->name;
    }

    /** Like a toolkit's close: its own signal runs the close path, here synchronously. */
    protected function closeNative(): void
    {
        $this->steps[] = 'closeNative';
        if ($this->fail_close) {
            throw new RuntimeException('native close failed');
        }
        if ($this->allowsUserClose()) {
            $this->closed();
        }
    }

    /** The content column's native, while it is in the content area. */
    public ?object $mounted = null;

    protected function mountContent(ColumnContract $content): void
    {
        $this->mounted = $content->native();
    }

    protected function unmountContent(ColumnContract $content): void
    {
        $this->mounted = null;
    }

    protected function beforeClose(): void
    {
        $this->steps[] = 'beforeClose';
    }

    protected function removeContent(): void
    {
        $this->steps[] = 'removeContent';
        parent::removeContent();
    }

    protected function releaseNative(): void
    {
        $this->steps[] = 'releaseNative';
        $this->window = null;
    }

    protected function live(): object
    {
        return $this->window ?? throw new RuntimeException("Fake window '{$this->name}' is closed.");
    }
}
