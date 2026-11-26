<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKWebView;

/**
 * A web view with a pretend engine: what was loaded is kept, and finish()/fail() play the
 * engine's report. FakeWebView::$engine = false acts as a toolkit with no web engine.
 */
class FakeWebView extends TKWebView
{
    use FakeNative;

    public static bool $engine = true;

    private ?string $shown = null;

    private ?string $page_title = null;

    private bool $loading = false;

    public function supported(): bool
    {
        return self::$engine;
    }

    public function url(): ?string
    {
        return $this->shown;
    }

    public function title(): ?string
    {
        return $this->page_title;
    }

    public function isLoading(): bool
    {
        return $this->loading;
    }

    /** The engine finished the page it was given. */
    public function finish(?string $title = null): void
    {
        $this->loading = false;
        $this->page_title = $title;
        $this->loaded($this->shown);
    }

    /** The engine could not show the page it was given. */
    public function fail(string $reason): void
    {
        $this->loading = false;
        $this->failed($this->shown, $reason);
    }

    protected function applyLoad(string $url): void
    {
        $this->record('applyLoad', $url);
        $this->shown = $url;
        $this->loading = true;
    }

    protected function applyLoadHtml(string $html, ?string $baseUrl): void
    {
        $this->record('applyLoadHtml', $html, $baseUrl);
        $this->shown = $baseUrl;
        $this->loading = true;
    }

    protected function applyReload(): void
    {
        $this->record('applyReload');
        $this->loading = true;
    }

    protected function applyStop(): void
    {
        $this->record('applyStop');
        $this->loading = false;
    }
}
