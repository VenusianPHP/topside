<?php

declare(strict_types=1);

use Surface\Contracts\HumanInput\InputContext;
use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\HumanInput\Mail\KeyChanged;
use Surface\Contracts\HumanInput\Mail\PointerButtonChanged;
use Surface\Contracts\HumanInput\Mail\PointerMoved;
use Surface\Contracts\HumanInput\Mail\PointerScrolled;
use Surface\Contracts\HumanInput\Mail\TextTyped;
use Surface\Contracts\HumanInput\Modifiers;
use Surface\Contracts\HumanInput\MouseButton;
use Surface\Contracts\HumanInput\ScrollPhase;
use Surface\HumanInput\InputChannel;

/** A stage's channel from an AppKit presentation, and the mail it sent. */
function appkitChannel(?array &$mail = null): InputChannel
{
    $manager = inputManager(['human-input.backend' => 'appkit', 'human-input.pads' => 'fakepads'], 'mac', $mail);
    $manager->resolve(new InputContext('appkit', 'velvet', 'mac'));
    $manager->pads();

    return $manager->channel(plainStage());
}

it('reads a press until the frame that read it settles', function () {
    $channel = appkitChannel();
    $channel->key(Key::A, true);

    expect($channel->keyboard()->isDown(Key::A))->toBeTrue()
        ->and($channel->keyboard()->isPressed(Key::A))->toBeTrue()
        ->and($channel->keyboard()->pressedKeys())->toBe([Key::A]);

    $channel->settle();

    expect($channel->keyboard()->isDown(Key::A))->toBeTrue()
        ->and($channel->keyboard()->isPressed(Key::A))->toBeFalse();
});

it('keeps a tap that went down and up inside one frame', function () {
    $channel = appkitChannel();
    $channel->key(Key::SPACE, true);
    $channel->key(Key::SPACE, false);

    expect($channel->keyboard()->isDown(Key::SPACE))->toBeFalse()
        ->and($channel->keyboard()->isPressed(Key::SPACE))->toBeTrue()
        ->and($channel->keyboard()->wasReleased(Key::SPACE))->toBeTrue();
});

it('keeps typed text and the modifiers for one frame', function () {
    $channel = appkitChannel();
    $channel->modifiers(new Modifiers(shift: true));
    $channel->text('H');
    $channel->text('i');

    expect($channel->keyboard()->text())->toBe('Hi')
        ->and($channel->keyboard()->modifiers()->shift)->toBeTrue();

    $channel->settle();

    expect($channel->keyboard()->text())->toBe('')
        ->and($channel->keyboard()->modifiers()->shift)->toBeTrue();
});

it('sends a change once, not a report of the same state', function () {
    $channel = appkitChannel($mail);
    $channel->key(Key::A, true);
    $channel->key(Key::A, true);
    $channel->button(MouseButton::LEFT, true);
    $channel->button(MouseButton::LEFT, true);
    $channel->text('a');

    expect(array_map(fn (object $m): string => $m::class, $mail))->toBe([KeyChanged::class, PointerButtonChanged::class, TextTyped::class]);
});

it('adds motion between pointer reports when either end is on the stage, but not the first report', function () {
    $channel = appkitChannel($mail);
    $channel->pointer(10, 10, false);
    $channel->pointer(30, 10, false);
    $channel->pointer(10, 10, false);
    $channel->pointer(12, 15);
    $channel->pointer(20, 20);

    expect($channel->mouse()->motion())->toBe(['dx' => 10.0, 'dy' => 10.0])
        ->and([$channel->mouse()->x(), $channel->mouse()->y(), $channel->mouse()->inside()])->toBe([20.0, 20.0, true])
        ->and($mail[4])->toEqual(new PointerMoved($channel->stage(), 20, 20, true));

    $channel->settle();

    expect($channel->mouse()->motion())->toBe(['dx' => 0.0, 'dy' => 0.0]);
});

it('puts a button press where the pointer is, with the keys held', function () {
    $channel = appkitChannel($mail);
    $channel->modifiers(new Modifiers(meta: true));
    $channel->pointer(3, 4);
    $channel->button(MouseButton::RIGHT, true);

    expect(end($mail))->toEqual(new PointerButtonChanged($channel->stage(), MouseButton::RIGHT, true, 3.0, 4.0, new Modifiers(meta: true)))
        ->and($channel->mouse()->isPressed(MouseButton::RIGHT))->toBeTrue();
});

it('releases everything held when the stage loses focus, each with its mail', function () {
    $channel = appkitChannel($mail);
    $channel->key(Key::W, true);
    $channel->button(MouseButton::LEFT, true);
    $mail = [];

    $channel->releaseAll();

    expect($channel->keyboard()->downKeys())->toBe([])
        ->and($channel->mouse()->isDown(MouseButton::LEFT))->toBeFalse()
        ->and($channel->mouse()->wasReleased(MouseButton::LEFT))->toBeTrue()
        ->and(array_map(fn (object $m): string => $m::class, $mail))->toBe([KeyChanged::class, PointerButtonChanged::class]);
});

it('sends nothing once closed', function () {
    $channel = appkitChannel($mail);
    $channel->close();
    $channel->key(Key::A, true);

    expect($mail)->toBe([]);
});

it('reads a notched wheel in notches, and a gesture with its phases', function () {
    $channel = appkitChannel($mail);
    $mouse = $channel->mouse();

    $channel->scroll(0, 1, precise: false);
    $channel->scroll(0, 2, precise: false);
    expect($mouse->wheel())->toBe(['dx' => 0.0, 'dy' => 3.0])
        ->and($mouse->precise())->toBeFalse()
        ->and($mouse->phase())->toBe(ScrollPhase::NONE);

    $channel->settle();
    $channel->scroll(0, 12.5, precise: true, inverted: true, phase: ScrollPhase::BEGAN);
    $channel->scroll(1, 7.5, precise: true, inverted: true, phase: ScrollPhase::CHANGED);
    expect($mouse->wheel())->toBe(['dx' => 1.0, 'dy' => 20.0])
        ->and($mouse->precise())->toBeTrue()
        ->and($mouse->inverted())->toBeTrue()
        ->and($mouse->phase())->toBe(ScrollPhase::CHANGED)
        ->and(end($mail))->toEqual(new PointerScrolled($channel->stage(), 1, 7.5, true, true, ScrollPhase::CHANGED));
});

it('keeps a gesture in its phase across frames until it ends', function () {
    $channel = appkitChannel();
    $mouse = $channel->mouse();

    $channel->scroll(0, 4, true, false, ScrollPhase::CHANGED);
    $channel->settle();
    expect($mouse->phase())->toBe(ScrollPhase::CHANGED)
        ->and($mouse->wheel())->toBe(['dx' => 0.0, 'dy' => 0.0]);

    $channel->scroll(0, 0, true, false, ScrollPhase::MOMENTUM_ENDED);
    expect($mouse->phase())->toBe(ScrollPhase::MOMENTUM_ENDED);
    $channel->settle();
    expect($mouse->phase())->toBe(ScrollPhase::NONE);
});

it('releases only the keys when the stage loses the keyboard, keeping a captured button down', function () {
    $channel = appkitChannel($mail);
    $channel->key(Key::W, true);
    $channel->modifiers(new Modifiers(shift: true));
    $channel->button(MouseButton::LEFT, true);
    $mail = [];

    $channel->releaseKeys();

    expect($channel->keyboard()->downKeys())->toBe([])
        ->and($channel->keyboard()->wasReleased(Key::W))->toBeTrue()
        ->and($channel->keyboard()->modifiers()->shift)->toBeFalse()
        ->and($channel->mouse()->isDown(MouseButton::LEFT))->toBeTrue()
        ->and(array_map(fn (object $m): string => $m::class, $mail))->toBe([KeyChanged::class]);
});

it('sends nothing for a pointer report of where the pointer already is', function () {
    $channel = appkitChannel($mail);
    $channel->pointer(5, 5);
    $channel->pointer(5, 5);
    $channel->pointer(5, 5, false);

    expect($mail)->toEqual([
        new PointerMoved($channel->stage(), 5, 5, true),
        new PointerMoved($channel->stage(), 5, 5, false),
    ]);
});
