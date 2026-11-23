<?php

namespace Venusian\Topside\Tests\Fakes;

use Surface\Toolkits\ToolkitSession;

class FakeSession extends ToolkitSession
{
    public int $bootstraps = 0;

    protected function bootstrapToolkitLibrary(): void
    {
        $this->bootstraps++;
        $this->application = new FakeToolkit();
    }

    public function application(): FakeToolkit
    {
        return $this->application;
    }

    /**
     * Mail held for a loop not yet joined, taken out.
     *
     * @return list<object>
     */
    public function takeOutbox(): array
    {
        [$mail, $this->outbox] = [$this->outbox, []];

        return $mail;
    }
}
