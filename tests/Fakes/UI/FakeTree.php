<?php

namespace Venusian\Topside\Tests\Fakes\UI;

use Surface\Toolkits\UI\TKTree;

class FakeTree extends TKTree
{
    use FakeNative;

    protected function applyNodes(array $nodes): void
    {
        $this->record('applyNodes', $nodes);
        $this->choseNode(null);
    }

    protected function applyExpanded(string $id, bool $expanded): void
    {
        $this->record('applyExpanded', $id, $expanded);
        $this->openedNode($id, ! $expanded);
    }

    protected function applySelected(?string $id): void
    {
        $this->record('applySelected', $id);
    }

    /** What the user does to the native. */
    public function nativeChose(?string $id): void
    {
        $this->choseNode($id);
    }

    /** What the user does to the native. */
    public function nativeOpened(string $id, bool $open): void
    {
        $this->openedNode($id, $open);
    }

    /** What the user does to the native. */
    public function nativeActivated(string $id): void
    {
        $this->activatedNode($id);
    }
}
