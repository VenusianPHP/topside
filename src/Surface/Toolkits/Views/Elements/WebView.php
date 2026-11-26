<?php

namespace Surface\Toolkits\Views\Elements;

use Closure;
use Surface\Contracts\Toolkits\UI\TKWebView;
use Surface\Contracts\Toolkits\UI\TKPrimitive;
use Surface\Contracts\Toolkits\UI\TKPrimitiveGroup;
use Surface\Contracts\Toolkits\Mail\Views\WebViewFailed;
use Surface\Contracts\Toolkits\Mail\Views\WebViewLoaded;

/**
 * A web view: a page by URL, or HTML with the base URL it resolves against. A change of
 * either loads again; the same page across renders is not reloaded.
 *
 *     <WebView :url="$page" @loaded="ready" @failed="fallBack" />
 *     <WebView :html="$player" :baseUrl="$origin" />
 *
 * Where the toolkit has no web engine it shows nothing and @failed runs.
 */
class WebView extends Element
{
    final public function __construct() {}

    public static function make(): static
    {
        return new static();
    }

    public function build(TKPrimitiveGroup $parent, string $name): TKPrimitive
    {
        return $parent->webView($name);
    }

    public function kind(): string
    {
        return 'webView';
    }

    /**
     * The page to show, an absolute URL. HTML, when given too, wins.
     */
    public function url(?string $url): static
    {
        return $this->set('url', $url);
    }

    /**
     * HTML to show, resolved against baseUrl() (also the origin its frames see).
     */
    public function html(?string $html): static
    {
        return $this->set('html', $html);
    }

    public function baseUrl(?string $url): static
    {
        return $this->set('baseUrl', $url);
    }

    /**
     * Run $handler when a page has loaded, with the WebViewLoaded mail.
     *
     * @param Closure(WebViewLoaded): mixed $handler
     */
    public function onLoaded(Closure $handler): static
    {
        return $this->on('loaded', $handler);
    }

    /**
     * Run $handler when a page could not be shown, or there is no web engine, with the
     * WebViewFailed mail (its reason, when the engine gives one).
     *
     * @param Closure(WebViewFailed): mixed $handler
     */
    public function onFailed(Closure $handler): static
    {
        return $this->on('failed', $handler);
    }

    protected function tracked(): array
    {
        return ['page'];
    }

    protected function setters(): array
    {
        return parent::setters() + [
            // What to load is one thing, the page: it is loaded from there.
            'url' => static fn () => null,
            'html' => static fn () => null,
            'baseUrl' => static fn () => null,
            'page' => static fn (TKWebView $p, ?array $page) => match ($page[0] ?? null) {
                'html' => $p->loadHtml($page[1], $page[2]),
                'url' => $p->load($page[1]),
                default => null,
            },
        ];
    }

    protected function defaults(): array
    {
        return parent::defaults() + ['url' => null, 'html' => null, 'baseUrl' => null, 'page' => null];
    }

    protected function valueOf(string $prop): mixed
    {
        if ($prop !== 'page') {
            return parent::valueOf($prop);
        }

        $html = parent::valueOf('html');
        $url = parent::valueOf('url');

        return match (true) {
            ! is_null($html) => ['html', $html, parent::valueOf('baseUrl')],
            ! is_null($url) => ['url', $url],
            default => null,
        };
    }
}
