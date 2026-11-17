<?php

namespace Surface\Rendering\Recording\Drivers;

use Surface\Contracts\Rendering\Recording\Queue;
use Surface\Contracts\Rendering\RenderingException;
use Surface\Rendering\Recording\Queues\CLandQueue;
use Surface\Rendering\Recording\Drivers\QueueRecordingDriver;

/**
 * VelvetGE's recorder over its queue in C: ext-pixel-queue holds the frame, records the plain
 * shapes and compares frames. The commands and the regions are the native driver's.
 */
class CLandRecordingDriver extends QueueRecordingDriver
{
    /**
     * @throws RenderingException When ext-pixel-queue is not loaded, or too old to record text: 'auto' then passes over this driver.
     */
    public function __construct()
    {
        extension_loaded('pixel_queue') || throw new RenderingException('ext-pixel-queue is not loaded in this PHP.');
        method_exists(\PixelQueue::class, 'glyphs') || throw new RenderingException('ext-pixel-queue is too old: it has no PixelQueue::glyphs(), which records text.');
    }

    public function driver(): string
    {
        return 'c';
    }

    public function queue(): Queue
    {
        return new CLandQueue();
    }
}
