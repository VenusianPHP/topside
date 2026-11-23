<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKImage;
use Surface\Contracts\Toolkits\UI\ImageScaling;

class FakeImage extends TKImage
{
    use FakeNative;

    protected function applyFile(?string $file): void
    {
        $this->record('applyFile', $file);
    }

    protected function applyScaling(ImageScaling $scaling): void
    {
        $this->record('applyScaling', $scaling);
    }
}
