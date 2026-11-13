<?php

namespace Surface\Contracts\Toolkits\Mail\Views;

use Surface\Contracts\Toolkits\Mail\PrimitiveMail;
/**
 * The user dragged a divider of a split view; posted once the drag ends.
 */
readonly class SplitMoved extends PrimitiveEventOccurred implements PrimitiveMail
{
    /**
     * @param list<float> $positions each divider as a fraction of the split's length
     */
    public function __construct(
        string $window,
        string $path,
        string $uuid,
        public array $positions,
    ) {
        parent::__construct($window, $path, $uuid);
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return "view.split-moved.{$this->window}.{$this->path}";
    }
}
