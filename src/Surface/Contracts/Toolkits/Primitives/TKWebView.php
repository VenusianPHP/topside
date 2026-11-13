<?php

namespace Surface\Contracts\Toolkits\Primitives;

use Surface\Contracts\Toolkits\Primitives\TKPrimitive;
use Surface\Contracts\Toolkits\ToolkitRuntimeException;

/**
 * Web content in a view: a page by URL, or HTML with the base URL it resolves against (and
 * the origin its frames see). Posts WebViewLoaded and WebViewFailed as the engine reports them;
 * url(), title() and isLoading() are the engine's last report.
 */
interface TKWebView extends TKPrimitive
{
    /**
     * @param string $url an absolute URL (http, https, file)
     * @return $this
     * @throws ToolkitRuntimeException When $url is not an absolute URL.
     */
    public function load(string $url): static;

    /**
     * @param string $html
     * @param string|null $baseUrl what relative links resolve against, and the page's origin; null: none
     * @return $this
     * @throws ToolkitRuntimeException When $baseUrl is given and is not an absolute URL.
     */
    public function loadHtml(string $html, ?string $baseUrl = null): static;

    /**
     * The page shown, as the engine reports it; null before anything loaded.
     * @return string|null
     */
    public function url(): ?string;

    /**
     * The page's title, as the engine reports it; null before one is known.
     * @return string|null
     */
    public function title(): ?string;

    /**
     * @return bool
     */
    public function isLoading(): bool;

    /**
     * @return $this
     */
    public function reload(): static;

    /**
     * @return $this
     */
    public function stop(): static;
}
