<?php

declare(strict_types=1);

use Surface\Contracts\HumanInput\GamepadAxis;
use Surface\Contracts\HumanInput\GamepadButton;
use Surface\Contracts\HumanInput\HumanInputException;
use Surface\Contracts\HumanInput\InputContext;
use Surface\Contracts\HumanInput\Mail\PadConnected;
use Surface\Contracts\HumanInput\Mail\PadDisconnected;
use Surface\HumanInput\Devices\GamePad;

/** @return array{0: Surface\HumanInput\HumanInputManager, 1: FakePadSource} */
function padded(?array &$mail = null): array
{
    $manager = inputManager(['human-input.backend' => 'appkit', 'human-input.pads' => 'fakepads'], 'mac', $mail);
    $manager->resolve(new InputContext('appkit', 'velvet', 'mac'));

    return [$manager, $manager->pads()];
}

it('gives each stage the press once, however many stages read the pad', function () {
    [$manager, $pads] = padded();
    $pad = $pads->plug(fakeController());
    $one = $manager->channel(plainStage());
    $two = $manager->channel(plainStage());

    $pad->update(GamepadButton::SOUTH, true);

    expect($one->gamePads()['pad-1']->isPressed(GamepadButton::SOUTH))->toBeTrue()
        ->and($two->gamePads()['pad-1']->isPressed(GamepadButton::SOUTH))->toBeTrue();

    $one->settle();

    expect($one->gamePads()['pad-1']->isPressed(GamepadButton::SOUTH))->toBeFalse()
        ->and($two->gamePads()['pad-1']->isPressed(GamepadButton::SOUTH))->toBeTrue()
        ->and($one->gamePads()['pad-1']->isDown(GamepadButton::SOUTH))->toBeTrue();
});

it('reads sticks and triggers as levels, clamped', function () {
    [$manager, $pads] = padded();
    $pad = $pads->plug(fakeController());
    $pad->setAxis(GamepadAxis::LEFT_X, -1.4);
    $pad->setAxis(GamepadAxis::RIGHT_TRIGGER, 0.25);
    $controller = $manager->channel(plainStage())->gameControllers()['pad-1'];

    expect($controller->leftStick())->toBe(['x' => -1.0, 'y' => 0.0])
        ->and($controller->rightTrigger())->toBe(0.25);
});

it('lists a pad without sticks as a game pad only', function () {
    [$manager, $pads] = padded();
    $pads->plug(new GamePad('micro', 'Micro', [GamepadButton::SOUTH]));
    $channel = $manager->channel(plainStage());

    expect(array_keys($channel->gamePads()))->toBe(['micro'])
        ->and($channel->gameControllers())->toBe([])
        ->and($channel->gamePads()['micro']->supports(GamepadButton::NORTH))->toBeFalse()
        ->and(fn () => $pads->pads()['micro']->update(GamepadButton::NORTH, true))
        ->toThrow(HumanInputException::class, "'Micro' has no north button.");
});

it('mails the pads that came and went, as it polls', function () {
    [$manager, $pads] = padded($mail);
    $pads->plug(fakeController('a'));
    $manager->poll();
    $pads->unplug('a');
    $pads->plug(fakeController('b'));
    $manager->poll();

    expect($mail)->toEqual([
        new PadConnected('a', 'Fake Controller', true),
        new PadDisconnected('a', 'Fake Controller'),
        new PadConnected('b', 'Fake Controller', true),
    ]);
});

it('releases a pad that goes, and drops it from every stage', function () {
    [$manager, $pads] = padded();
    $pad = $pads->plug(fakeController());
    $channel = $manager->channel(plainStage());
    $pad->update(GamepadButton::START, true);
    $channel->gamePads();

    $pads->unplug('pad-1');

    expect($pad->buttons['start']->down)->toBeFalse()
        ->and($channel->gamePads())->toBe([]);
});
