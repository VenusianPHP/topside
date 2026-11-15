<?php

namespace Surface\Rendering\Recording\Drivers;

use Surface\Contracts\Rendering\Recording\Queue;
use Surface\Rendering\Recording\RecordingDriver;
use Surface\Rendering\Recording\DrawCommandQueue;
use Surface\Contracts\Rendering\Recording\Recorder;

/**
 * A recording driver made of VelvetGE's recorder and a queue. A package that brings a queue (an
 * extension, or a library) writes a Queue, or a ShapeQueue, and a driver of two methods:
 *
 *     class FastRecordingDriver extends QueueRecordingDriver
 *     {
 *         public function __construct()
 *         {
 *             extension_loaded('fast') || throw new RenderingException('ext-fast is not loaded.');
 *         }
 *
 *         public function driver(): string { return 'fast'; }
 *
 *         public function queue(): Queue { return new FastQueue(); }
 *     }
 *
 * and registers it: Recorders::manager()->extend('fast', fn () => new FastRecordingDriver()).
 * A constructor that throws RenderingException tells 'auto' the driver cannot run here.
 *
 * The queue holds the frame and compares frames. VelvetGE's recorder lowers every draw call the
 * queue does not record itself, so a queue of Queue's methods alone is a whole recorder.
 */
abstract class QueueRecordingDriver extends RecordingDriver
{
    /** A queue of its own for one recorder. */
    abstract public function queue(): Queue;

    public function recorder(): Recorder
    {
        return new DrawCommandQueue($this->queue());
    }
}
