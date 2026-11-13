<?php

declare(strict_types=1);

require_once __DIR__.'/Fixtures/FakeFramebuffers.php';
require_once __DIR__.'/Fixtures/FakeManagers.php';
require_once __DIR__.'/Fixtures/FakeScanners.php';
require_once __DIR__.'/Fixtures/FakeMarchers.php';
require_once __DIR__.'/Fixtures/RecordingEngine.php';
require_once __DIR__.'/Fixtures/FakeGpu.php';

/** A full framebuffer with its bytes in PHP. */
function nativeFull(\Surface\Contracts\Rendering\Framebuffers\FormatSpec $format, int $width, int $height): \Surface\VelvetGE\Framebuffers\FullFramebuffer
{
    return (new \Surface\VelvetGE\Drivers\Framebuffers\PHPLandFramebufferDriver())->full($format, $width, $height);
}

// TOPSIDE_STROKER=ext runs the whole suite with ext-rasterize's stroker in place of PHP's.
if (getenv('TOPSIDE_STROKER') === 'ext') {
    \Surface\Rendering\Rasterization\Stroker::use(new \Surface\VelvetGE\Rasterize\CLandStroker());
}
