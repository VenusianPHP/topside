<?php

namespace Surface\Contracts\Toolkits\Mail\Views;

/**
 * A web view could not show a page, or has no web engine to show one with (its supported()
 * is false).
 */
readonly class WebViewFailed extends PrimitiveEventOccurred
{
    /**
     * @param string|null $url the page it was asked for, when known
     * @param string|null $reason the engine's own words, when it gives any
     */
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public ?string $url = null,
        public ?string $reason = null,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    public function name(): string
    {
        return "view.web.failed.{$this->window}.{$this->path}";
    }
}
