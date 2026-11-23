<?php

declare(strict_types=1);

use Surface\Toolkits\EventLoop\ToolkitPoller;
use Surface\Toolkits\EventLoop\ToolkitPump;
use Venusian\Topside\Tests\Fakes\FakeSession;

beforeEach(function (): void {
    $this->session = (new FakeSession($this->app))->connect();
    $this->session->application()->during_pump = fn () => $this->session->postLatest('k', $this->mail = new stdClass());
});

it('sleeps in the toolkit\'s own wait for the loop\'s budget, then sends latest-only mail', function (): void {
    (new ToolkitPump($this->session))->sleep(8_000_000);

    expect($this->session->application()->pumps)->toBe([8_000_000])
        ->and($this->session->takeOutbox())->toBe([$this->mail]);
});

it('ticks without waiting, then sends latest-only mail', function (): void {
    (new ToolkitPump($this->session))->tick();

    expect($this->session->application()->pumps)->toBe([0])
        ->and($this->session->takeOutbox())->toBe([$this->mail]);
});

it('polls a toolkit that cannot sleep without waiting, then sends latest-only mail', function (): void {
    (new ToolkitPoller($this->session))->tick();

    expect($this->session->application()->pumps)->toBe([0])
        ->and($this->session->takeOutbox())->toBe([$this->mail]);
});
