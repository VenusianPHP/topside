<?php

declare(strict_types=1);

use Surface\Contracts\HumanInput\HumanInputException;
use Surface\Contracts\HumanInput\InputContext;

/*
 * The table, derived: no row is written into topside. Each comes from what the configured backend
 * declares (where it runs, whom it reaches) against the toolkit, canvas engine and OS.
 */

dataset('presentations', [
    // platform, toolkit, canvas engine, backend, can
    ['mac', 'appkit', 'velvet', 'appkit', true],
    ['mac', 'gtk', 'velvet', 'sdl3', false],
    ['mac', 'gtk', 'velvet', null, false],
    ['mac', 'qt', 'velvet', 'sdl3', false],
    ['mac', 'qt', 'velvet', null, false],
    ['mac', 'raven', 'velvet', 'sdl3', false],
    ['mac', 'raven', 'velvet', null, false],
    ['mac', 'raven', 'velvet', 'raven', true],
    ['mac', 'gtk', 'velvet', 'evdev', false],
    ['linux', 'gtk', 'velvet', 'evdev', true],
    ['linux', 'gtk', 'sdl3', 'sdl3', true],
    ['linux', 'gtk', 'velvet', 'sdl3', false],
    ['linux', 'gtk', 'velvet', null, false],
    ['linux', 'qt', 'velvet', 'evdev', true],
    ['linux', 'qt', 'sdl3', 'sdl3', true],
    ['linux', 'qt', 'velvet', null, false],
    ['linux', 'raven', 'velvet', 'evdev', true],
    ['linux', 'raven', 'sdl3', 'sdl3', true],
    ['linux', 'raven', 'velvet', 'raven', true],
    ['linux', 'gtk', 'velvet', 'appkit', false],
    ['linux', 'gtk', 'velvet', 'raven', false],
]);

it('derives whether a presentation can have input', function (string $os, string $toolkit, string $engine, ?string $backend, bool $can) {
    $manager = inputManager(['human-input.backend' => $backend], $os);
    $context = new InputContext($toolkit, $engine, $os);

    if ($can) {
        expect($manager->resolve($context)->name())->toBe($backend);
    } else {
        expect(fn () => $manager->resolve($context))->toThrow(HumanInputException::class);
    }
})->with('presentations');

it('says why it cannot', function () {
    $context = new InputContext('gtk', 'velvet', 'mac');

    expect(fn () => inputManager([], 'mac')->resolve($context))
        ->toThrow(HumanInputException::class, 'names no backend')
        ->and(fn () => inputManager(['human-input.backend' => 'glfw'], 'mac')->resolve($context))
        ->toThrow(HumanInputException::class, "No input backend named 'glfw' is registered (registered: appkit, raven, evdev, sdl3)")
        ->and(fn () => inputManager(['human-input.backend' => 'evdev'], 'mac')->resolve($context))
        ->toThrow(HumanInputException::class, "The 'evdev' input backend cannot run for the 'gtk' toolkit with 'velvet' canvases on mac: it does not run on mac.")
        ->and(fn () => inputManager(['human-input.backend' => 'appkit'], 'mac')->resolve($context))
        ->toThrow(HumanInputException::class, "The 'appkit' input backend does not serve the 'gtk' toolkit");
});

it('has no channel before it resolves', function () {
    expect(fn () => inputManager(['human-input.backend' => 'appkit'])->channel(plainStage()))
        ->toThrow(HumanInputException::class, 'no backend yet');
});

it('gives a stage one channel, connecting the backend', function () {
    $manager = inputManager(['human-input.backend' => 'appkit']);
    $backend = $manager->resolve(new InputContext('appkit', 'velvet', 'mac'));
    $stage = plainStage();

    $channel = $manager->channel($stage);

    expect($backend->connected())->toBeTrue()
        ->and($manager->channel($stage))->toBe($channel)
        ->and($manager->channel(plainStage()))->not->toBe($channel)
        ->and($channel->stage())->toBe($stage);
});

it('makes the configured pad source, or none', function () {
    expect(inputManager([])->pads())->toBeNull()
        ->and(inputManager(['human-input.pads' => 'fakepads'])->pads())->toBeInstanceOf(FakePadSource::class)
        ->and(fn () => inputManager(['human-input.pads' => 'gamecontroller'])->pads())
        ->toThrow(HumanInputException::class, "No pad source named 'gamecontroller' is registered (registered: fakepads)");
});

it('closes every channel as it is destroyed', function () {
    $manager = inputManager(['human-input.backend' => 'appkit']);
    $manager->resolve(new InputContext('appkit', 'velvet', 'mac'));
    $channel = $manager->channel(plainStage());

    $manager->destroy();

    expect($channel->closed())->toBeTrue()
        ->and(fn () => $channel->keyboard())->toThrow(HumanInputException::class, 'closed');
});
