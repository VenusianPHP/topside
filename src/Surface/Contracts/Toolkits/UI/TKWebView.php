<?php

namespace Surface\Contracts\Toolkits\UI;

use Surface\Contracts\Toolkits\UI\TKPrimitive;
use RuntimeException;

/**
 * Web content in a view: a page by URL, or HTML with the base URL it resolves against (and
 * the origin its frames see). Posts WebViewLoaded and WebViewFailed as the engine reports them;
 * url(), title() and isLoading() are the engine's last report.
 *
 * A toolkit with no web engine still makes one: supported() is false, it shows nothing, and
 * every load posts WebViewFailed, so the view can offer something else (a link out).
 */
interface TKWebView extends TKPrimitive
{
    /**
     * @param string $url an absolute URL (http, https, file)
     * @return $this
     * @throws RuntimeException When $url is not an absolute URL.
     */
    public function load(string $url): static;

    /**
     * @param string $html
     * @param string|null $baseUrl what relative links resolve against, and the page's origin; null: none
     * @return $this
     * @throws RuntimeException When $baseUrl is given and is not an absolute URL.
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

    /**
     * Whether this toolkit has a web engine to show pages with.
     * @return bool
     */
    public function supported(): bool;
}
