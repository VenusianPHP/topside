<?php

namespace Surface\Toolkits\UI;

use Surface\Toolkits\ToolkitException;
use Surface\Contracts\Toolkits\UI\Placement;
use Surface\Contracts\Toolkits\ToolkitPoweredWindow;
use Surface\Contracts\Toolkits\Mail\Views\WebViewFailed;
use Surface\Contracts\Toolkits\Mail\Views\WebViewLoaded;
use Surface\Contracts\Toolkits\UI\TKWebView as PrimitiveContract;

/**
 * A web view. The toolkit's engine holds the page: url(), title() and isLoading() read it.
 * The toolkit tells this one how a load ended by calling loaded() or failed().
 *
 * A toolkit with no web engine answers supported() with false: a load then posts
 * WebViewFailed at once and nothing reaches the toolkit.
 */
abstract class TKWebView extends TKPrimitive implements PrimitiveContract
{
    public const string NO_ENGINE = 'This toolkit has no web engine.';

    public function __construct(
        string $name,
        ToolkitPoweredWindow $window,
        ?TKPrimitiveGroup $parent = null,
        ?Placement $placement = null,
    ) {
        parent::__construct($name, $window, $parent, $placement);
    }

    /**
     * @throws ToolkitException When removed, or the URL is not absolute.
     */
    public function load(string $url): static
    {
        $this->guardLive();
        self::guardUrl($url, 'URL', $this->path());

        if (! $this->supported()) {
            $this->failed($url, self::NO_ENGINE);

            return $this;
        }

        $this->applyLoad($url);

        return $this;
    }

    /**
     * @throws ToolkitException When removed, or the base URL is given and not absolute.
     */
    public function loadHtml(string $html, ?string $baseUrl = null): static
    {
        $this->guardLive();

        if (! is_null($baseUrl)) {
            self::guardUrl($baseUrl, 'base URL', $this->path());
        }

        if (! $this->supported()) {
            $this->failed($baseUrl, self::NO_ENGINE);

            return $this;
        }

        $this->applyLoadHtml($html, $baseUrl);

        return $this;
    }

    public function reload(): static
    {
        $this->guardLive();

        if ($this->supported()) {
            $this->applyReload();
        }

        return $this;
    }

    public function stop(): static
    {
        $this->guardLive();

        if ($this->supported()) {
            $this->applyStop();
        }

        return $this;
    }

    public function supported(): bool
    {
        return true;
    }

    /**
     * The engine finished loading a page: post WebViewLoaded. Nothing from a view that is
     * going.
     */
    protected function loaded(?string $url): void
    {
        if ($this->isRemoved()) {
            return;
        }

        $this->window()->post(new WebViewLoaded($this->window()->name(), $this->path(), $this->uuid(), $url));
    }

    /**
     * The engine could not show a page: post WebViewFailed. Nothing from a view that is going.
     */
    protected function failed(?string $url, ?string $reason): void
    {
        if ($this->isRemoved()) {
            return;
        }

        $this->window()->post(new WebViewFailed($this->window()->name(), $this->path(), $this->uuid(), $url, $reason));
    }

    /**
     * @throws ToolkitException With no scheme, or with a space or control character in it.
     */
    private static function guardUrl(string $url, string $what, string $where): void
    {
        if (! preg_match('/^[a-z][a-z0-9+.-]*:[^\s\x00-\x1f]+$/iD', $url)) {
            throw new ToolkitException("A web view's {$what} is absolute (https://..., file://...), got '{$url}' for '{$where}'.");
        }
    }

    abstract protected function applyLoad(string $url): void;

    abstract protected function applyLoadHtml(string $html, ?string $baseUrl): void;

    abstract protected function applyReload(): void;

    abstract protected function applyStop(): void;
}
