<?php

namespace Surface\VelvetGE\Drivers\Recording;

use Surface\Contracts\Rendering\Recording\Queue;
use Surface\Contracts\Rendering\RenderingException;
use Surface\VelvetGE\Recording\CLandQueue;

/**
 * VelvetGE's recorder over its queue in C: ext-pixel-queue holds the frame, records the plain
 * shapes and compares frames. The commands and the regions are the native driver's.
 */
class CLandRecordingDriver extends QueueRecordingDriver
{
    /**
     * @throws RenderingException When ext-pixel-queue is not loaded: 'auto' then passes over this driver.
     */
    public function __construct()
    {
        extension_loaded('pixel_queue') || throw new RenderingException('ext-pixel-queue is not loaded in this PHP.');
    }

    public function driver(): string
    {
        return 'ext';
    }

    public function queue(): Queue
    {
        return new CLandQueue();
    }
}
