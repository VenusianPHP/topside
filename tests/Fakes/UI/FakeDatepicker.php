<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKDatepicker;

class FakeDatepicker extends TKDatepicker
{
    use FakeNative;

    public function nativePicked(int $year, int $month, int $day): void
    {
        $this->picked($year, $month, $day);
    }

    protected function applyDate(?\DateTimeImmutable $date): void
    {
        $this->record("applyDate", $date?->format("Y-m-d"));
        $this->picked(2000, 1, 1);    // a toolkit signals its own writes
    }
}
