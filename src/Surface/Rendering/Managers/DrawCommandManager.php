<?php

namespace Surface\Rendering\Managers;

use Surface\Contracts\Rendering\Recording\Recorder;
use Surface\Rendering\CommandRecorders\CCommandRecorder;
use Surface\Rendering\CommandRecorders\PHPCommandRecorder;
use Voyager\NutsAndBolts\Manager;

/**
 * The command recorders: 'c' on ext-comrec, 'php' in PHP, and 'auto', which is 'c' when ext-comrec is loaded and
 * 'php' otherwise. rendering.recorder.default names the one driver() answers.
 */
class DrawCommandManager extends Manager
{
    public function createCDriver(): CCommandRecorder
    {
        return new CCommandRecorder();
    }

    public function createPhpDriver(): PHPCommandRecorder
    {
        return new PHPCommandRecorder();
    }

    public function createAutoDriver(): Recorder
    {
        return extension_loaded('comrec') ? new CCommandRecorder() : new PHPCommandRecorder();
    }

    /**
     * A new recorder of that kind ('c', 'php', 'auto'; the configured one when null), made each call and kept by no
     * one: a recorder holds the frames it compared, so each stream of frames needs its own.
     */
    public function fresh(?string $name = null): Recorder
    {
        return $this->createDriver($name ?? $this->getDefaultDriver());
    }

    public function getDefaultDriver(): string
    {
        return $this->config->get('rendering.recorder.default', 'auto');
    }
}
