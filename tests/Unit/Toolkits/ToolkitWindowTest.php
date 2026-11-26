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

describe('alerts', function (): void {
    it('shows an alert without waiting, and posts nothing until it is answered', function (): void {
        $alert = new Surface\Contracts\Toolkits\Dialogs\Alert('Clear it?', buttons: ['Clear', 'Cancel']);
        $id = $this->window->alert($alert);

        expect($id)->toMatch('/^[0-9a-f]{16}$/')
            ->and($this->window->shown_alert)->toBe($alert)
            ->and($this->window->isAlerting())->toBeTrue()
            ->and(outbox())->toBe([]);
    });

    it('posts the answer under the alert\'s id and window, once', function (): void {
        $id = $this->window->alert(new Surface\Contracts\Toolkits\Dialogs\Alert('Clear it?', buttons: ['Clear', 'Cancel']));
        $this->window->nativeAlertAnswered(1, true);
        $this->window->nativeAlertAnswered(0);

        $mail = outbox();
        expect(mailNames($mail))->toBe(['AlertAnswered'])
            ->and($mail[0]->window())->toBe('main')
            ->and($mail[0]->id)->toBe($id)
            ->and($mail[0]->button)->toBe(1)
            ->and($mail[0]->suppressed)->toBeTrue()
            ->and($mail[0]->name())->toBe('window.alert-answered.main')
            ->and($this->window->isAlerting())->toBeFalse();
    });

    it('shows one alert at a time', function (): void {
        $this->window->alert(new Surface\Contracts\Toolkits\Dialogs\Alert('First'));

        expect(fn () => $this->window->alert(new Surface\Contracts\Toolkits\Dialogs\Alert('Second')))
            ->toThrow(Surface\Toolkits\ToolkitException::class, "Window 'main' already shows an alert: it can show one at a time.");
    });

    it('shows another once the first is answered', function (): void {
        $first = $this->window->alert(new Surface\Contracts\Toolkits\Dialogs\Alert('First'));
        $this->window->nativeAlertAnswered(0);

        expect($this->window->alert(new Surface\Contracts\Toolkits\Dialogs\Alert('Second')))->not->toBe($first);
    });

    it('is free to show another when the toolkit fails to show one', function (): void {
        $this->window->fail_alert = true;
        expect(fn () => $this->window->alert(new Surface\Contracts\Toolkits\Dialogs\Alert('First')))->toThrow(RuntimeException::class, 'native alert failed')
            ->and($this->window->isAlerting())->toBeFalse();
    });

    it('shows no alert once closed', function (): void {
        $this->window->userClose();

        expect(fn () => $this->window->alert(new Surface\Contracts\Toolkits\Dialogs\Alert('Late')))
            ->toThrow(Surface\Toolkits\ToolkitException::class, "Window 'main' is closed: it can show no alert.");
    });

    it('takes its alert down and answers it -1 when it closes first', function (): void {
        $id = $this->window->alert(new Surface\Contracts\Toolkits\Dialogs\Alert('Clear it?'));
        $this->window->userClose();
        $this->window->nativeAlertAnswered(0);

        $mail = outbox();
        expect(mailNames($mail))->toBe(['AlertAnswered', 'WindowClosed'])
            ->and($mail[0]->id)->toBe($id)
            ->and($mail[0]->button)->toBe(-1)
            ->and($mail[0]->suppressed)->toBeFalse()
            ->and($this->window->steps)->toBe(['bootstrap', 'showAlert', 'closeAlert', 'beforeClose', 'removeContent', 'releaseNative']);
    });
});

describe('an alert', function (): void {
    it('takes 1 to 3 buttons, the last one cancelling when none is named', function (): void {
        $alert = new Surface\Contracts\Toolkits\Dialogs\Alert('Save?', buttons: ['Save', 'Don\'t Save', 'Cancel']);

        expect($alert->cancel)->toBe(2)
            ->and($alert->default)->toBe(0)
            ->and(fn () => new Surface\Contracts\Toolkits\Dialogs\Alert('No', buttons: []))->toThrow(InvalidArgumentException::class, 'An alert takes 1 to 3 buttons, not 0 buttons.')
            ->and(fn () => new Surface\Contracts\Toolkits\Dialogs\Alert('No', buttons: ['a', 'b', 'c', 'd']))->toThrow(InvalidArgumentException::class, 'An alert takes 1 to 3 buttons, not 4 buttons.');
    });

    it('refuses a default or cancel outside its buttons', function (): void {
        expect(fn () => new Surface\Contracts\Toolkits\Dialogs\Alert('No', buttons: ['OK'], default: 1))->toThrow(InvalidArgumentException::class, 'Alert default 1 is outside its 1 buttons.')
            ->and(fn () => new Surface\Contracts\Toolkits\Dialogs\Alert('No', buttons: ['OK'], cancel: -1))->toThrow(InvalidArgumentException::class, 'Alert cancel -1 is outside its 1 buttons.')
            ->and(fn () => new Surface\Contracts\Toolkits\Dialogs\Alert('No', buttons: ['a' => 'OK']))->toThrow(InvalidArgumentException::class, 'Alert buttons must be a list.');
    });
});

describe('browse', function (): void {
    it('hands an http or https address to the toolkit', function (): void {
        $this->window->browse('https://example.com/page?a=1');
        $this->window->browse('HTTP://example.com');

        expect($this->window->opened)->toBe(['https://example.com/page?a=1', 'HTTP://example.com']);
    });

    it('refuses an address that is not http or https with a host', function (string $url): void {
        expect(fn () => $this->window->browse($url))
            ->toThrow(InvalidArgumentException::class, "'{$url}' is not an http or https address with a host.")
            ->and($this->window->opened)->toBe([]);
    })->with(['file:///etc/hosts', 'mailto:a@example.com', 'https://', 'example.com', '']);
});

describe('button styles', function (): void {
    it('frames DEFAULT and HELP, not BORDERLESS or LINK', function (): void {
        expect(array_map(fn (Surface\Contracts\Toolkits\UI\ButtonStyle $s) => [$s->value, $s->framed()], Surface\Contracts\Toolkits\UI\ButtonStyle::cases()))
            ->toBe([['default', true], ['borderless', false], ['link', false], ['help', true]]);
    });
});
