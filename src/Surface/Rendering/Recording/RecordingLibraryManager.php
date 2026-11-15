<?php

namespace Surface\Rendering\Recording;

use ReflectionException;
use Surface\Contracts\Rendering\RenderingException;
use Surface\Rendering\LibraryManager;
use Surface\Rendering\Recording\Drivers\CLandRecordingDriver;
use Surface\Rendering\Recording\Drivers\PHPLandRecordingDriver;

class RecordingLibraryManager extends LibraryManager
{
    public function createCDriver(): CLandRecordingDriver
    {
        extension_loaded('pixel_queue') || throw new RenderingException('ext-pixel-queue is not loaded in this PHP.');

        return new CLandRecordingDriver();
    }

    public function createPhpDriver(): PHPLandRecordingDriver
    {
        return new PHPLandRecordingDriver();
    }

    /**
     * The first driver that can run here: a registered library binding, then 'ext', then 'native'.
     *
     * @throws RenderingException When no registered driver can run.
     */
    public function createAutoDriver(): RecordingDriver
    {
        return $this->firstAvailable();
    }

    /**
     * @throws ReflectionException
     */
    public function getDefaultDriver(): ?string
    {
        return $this->config->get('rendering.recorder.default', 'auto');
    }
}
