<?php

namespace Surface\VelvetGE\Framebuffers;

use Surface\Contracts\Rendering\Framebuffers\DamageTrackingFramebuffer;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Framebuffers\PixelStoreMinter;
use Surface\Contracts\Rendering\Framebuffers\Region;

/**
 * One whole frame that records every write as a rect, so a host can send only
 * what changed: flushRegion() each rect of damage(), then beginEpoch().
 */
class DirtyFramebuffer extends StoreFramebuffer implements DamageTrackingFramebuffer
{
    protected DamageRecord $record;

    public function __construct(PixelStoreMinter $minter, FormatSpec $format, int $width, int $height)
    {
        parent::__construct($minter, $format, $width, $height);
        $this->record = new DamageRecord();
    }

    public function beginEpoch(): static
    {
        $this->record->clear();

        return $this;
    }

    public function damage(): array
    {
        return $this->record->regions($this->damageGranularity());
    }

    /** @return list<Region> The record as written, not snapped. */
    public function written(): array
    {
        return $this->record->written();
    }

    protected function touched(Region $region): void
    {
        $this->record->add($region);
    }
}
