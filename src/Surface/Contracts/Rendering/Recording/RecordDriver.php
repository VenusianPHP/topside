<?php

namespace Surface\Contracts\Rendering\Recording;

interface RecordDriver
{
    /** 'native', 'ext', or a library's name. */
    public function driver(): string;

    /** A recorder of its own for one painter: it holds that painter's open frame and its last two. */
    public function recorder(): Recorder;
}
