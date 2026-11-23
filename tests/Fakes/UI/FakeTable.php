<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKTable;

class FakeTable extends TKTable
{
    use FakeNative;

    protected function applyRows(array $cells): void
    {
        $this->record('applyRows', $cells);
        $this->choseRow(null);
    }

    protected function applySelectedRow(?int $row): void
    {
        $this->record('applySelectedRow', $row);
    }

    /** What the user does to the native. */
    public function nativeChose(?int $row): void
    {
        $this->choseRow($row);
    }
}
