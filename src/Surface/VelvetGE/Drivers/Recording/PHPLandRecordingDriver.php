<?php

namespace Surface\VelvetGE\Drivers\Recording;

use Surface\Contracts\Rendering\Recording\Queue;
use Surface\VelvetGE\Recording\PHPLandQueue;

/** VelvetGE's recorder over its queue in PHP. Always available: what 'auto' takes last. */
class PHPLandRecordingDriver extends QueueRecordingDriver
{
    public function driver(): string
    {
        return 'native';
    }

    public function queue(): Queue
    {
        return new PHPLandQueue();
    }
}
