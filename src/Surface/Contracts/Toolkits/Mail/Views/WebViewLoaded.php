<?php

namespace Surface\Contracts\Toolkits\Mail\Views;

use Surface\Contracts\Toolkits\Mail\PrimitiveMail;
/** A web view finished loading a page. */
readonly class WebViewLoaded extends PrimitiveEventOccurred implements PrimitiveMail
{
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public readonly ?string $url,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return "view.web-view-loaded.{$this->window}.{$this->path}";
    }
}
