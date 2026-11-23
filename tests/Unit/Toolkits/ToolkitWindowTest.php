<?php

declare(strict_types=1);

use Surface\Contracts\Toolkits\Mail\Windows\WindowClosed;
use Surface\Contracts\Toolkits\Mail\Windows\WindowCloseRequested;
use Surface\Contracts\Toolkits\Mail\Windows\WindowResized;
use Surface\Contracts\Toolkits\UI\TKColumn;
use Venusian\Topside\Tests\Fakes\FakeDriver;
use Venusian\Topside\Tests\Fakes\FakeWindow;

beforeEach(function (): void {
    $this->driver = new FakeDriver($this->app);
    /** @var FakeWindow $window */
    $this->window = $this->driver->open('main', 640, 480);
    $this->driver->session()->takeOutbox();
});

function outbox(): array
{
    return test()->driver->session()->takeOutbox();
}

it('builds its native window, then registers with the session', function (): void {
    $this->driver->session()->pump(0);

    expect($this->window->name())->toBe('main')
        ->and($this->window->steps)->toBe(['bootstrap'])
        ->and($this->window->isOpen())->toBeTrue()
        ->and($this->window->size())->toBe([640, 480])
        ->and($this->window->settles)->toBe(1);
});

it('closes on the user\'s close and runs the close path in order', function (): void {
    expect($this->window->userClose())->toBeTrue()
        ->and($this->window->steps)->toBe(['bootstrap', 'beforeClose', 'removeContent', 'releaseNative'])
        ->and($this->window->isOpen())->toBeFalse()
        ->and(mailNames(outbox()))->toBe(['WindowClosed'])
        ->and($this->driver->windows())->toBe([]);
});

it('stops being settled once closed', function (): void {
    $this->window->userClose();
    $this->driver->session()->pump(0);

    expect($this->window->settles)->toBe(0);
});

it('frees its name for a new window once closed', function (): void {
    $this->window->userClose();

    expect($this->driver->open('main', 10, 10))->not->toBe($this->window);
});

it('keeps the window open and asks the app while it confirms close', function (): void {
    expect($this->window->confirmClose())->toBe($this->window)
        ->and($this->window->isConfirmingClose())->toBeTrue()
        ->and($this->window->userClose())->toBeFalse()
        ->and($this->window->isOpen())->toBeTrue();

    $mail = outbox();
    expect($mail)->toHaveCount(1)
        ->and($mail[0])->toBeInstanceOf(WindowCloseRequested::class)
        ->and($mail[0]->window)->toBe('main');
});

it('closes from code while it confirms close', function (): void {
    $this->window->confirmClose();

    $this->window->close();

    expect($this->window->isOpen())->toBeFalse()
        ->and($this->window->steps)->toBe(['bootstrap', 'closeNative', 'beforeClose', 'removeContent', 'releaseNative'])
        ->and(mailNames(outbox()))->toBe(['WindowClosed']);
});

it('goes back to asking the app when a close from code fails', function (): void {
    $this->window->confirmClose();
    $this->window->fail_close = true;

    expect(fn () => $this->window->close())->toThrow(RuntimeException::class, 'native close failed');

    expect($this->window->isOpen())->toBeTrue()
        ->and($this->window->userClose())->toBeFalse()
        ->and(mailNames(outbox()))->toBe(['WindowCloseRequested']);
});

it('stops asking the app once confirm close is turned off', function (): void {
    $this->window->confirmClose();
    $this->window->confirmClose(false);

    expect($this->window->isConfirmingClose())->toBeFalse()
        ->and($this->window->userClose())->toBeTrue();
});

it('closes once however many times it is closed', function (): void {
    $this->window->close();
    $this->window->close();
    $this->window->nativeDestroyed();

    expect($this->window->steps)->toBe(['bootstrap', 'closeNative', 'beforeClose', 'removeContent', 'releaseNative'])
        ->and(mailNames(outbox()))->toBe(['WindowClosed']);
});

it('takes the close path when the toolkit destroys the window itself', function (): void {
    $this->window->nativeDestroyed();

    expect($this->window->isOpen())->toBeFalse()
        ->and(mailNames(outbox()))->toBe(['WindowClosed']);
});

it('names itself in the close mail', function (): void {
    $this->window->close();

    $mail = outbox();
    expect($mail[0])->toBeInstanceOf(WindowClosed::class)
        ->and($mail[0]->window)->toBe('main');
});

it('sends one resize per flush, with the last size', function (): void {
    $this->window->nativeResized(700, 500);
    $this->window->nativeResized(800, 600);
    $this->driver->session()->flushLatest();

    $mail = outbox();
    expect($mail)->toHaveCount(1)
        ->and($mail[0])->toBeInstanceOf(WindowResized::class)
        ->and([$mail[0]->window, $mail[0]->width, $mail[0]->height])->toBe(['main', 800, 600]);
});

it('drops a resize not yet sent when it closes', function (): void {
    $this->window->nativeResized(800, 600);
    $this->window->close();
    $this->driver->session()->flushLatest();

    expect(mailNames(outbox()))->toBe(['WindowClosed']);
});

it('refuses native access once closed', function (): void {
    $this->window->close();

    expect(fn () => $this->window->native())->toThrow(RuntimeException::class, 'closed')
        ->and(fn () => $this->window->setTitle('x'))->toThrow(RuntimeException::class, 'closed');
});

it('removes its content tree when it closes', function (): void {
    $content = $this->createMock(TKColumn::class);
    $content->expects($this->once())->method('remove');
    $this->window->adoptContent($content);

    $this->window->close();
    $this->window->close();
});
