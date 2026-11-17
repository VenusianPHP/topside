<?php

namespace Surface\Rendering\Recording\Drivers;

use Surface\Contracts\Rendering\Recording\Queue;
use Surface\Rendering\Recording\Queues\PHPLandQueue;
use Surface\Rendering\Recording\Drivers\QueueRecordingDriver;

/** VelvetGE's recorder over its queue in PHP. Always available: what 'auto' takes last. */
class PHPLandRecordingDriver extends QueueRecordingDriver
{
    public function driver(): string
    {
        return 'php';
    }

    public function queue(): Queue
    {
        return new PHPLandQueue();
    }
}
