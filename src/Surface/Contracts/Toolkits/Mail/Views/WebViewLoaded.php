<?php

namespace Surface\Contracts\Toolkits\Mail\Views;

/**
 * A web view finished loading a page: the page and what it refers to.
 */
readonly class WebViewLoaded extends PrimitiveEventOccurred
{
    /**
     * @param string|null $url the page shown, as the engine reports it
     */
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public ?string $url = null,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    public function name(): string
    {
        return "view.web.loaded.{$this->window}.{$this->path}";
    }
}
