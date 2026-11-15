<?php

declare(strict_types=1);

use Surface\Contracts\Graphics\ActorStage;
use Surface\Contracts\HumanInput\GamepadButton;
use Surface\Contracts\HumanInput\HumanInputException;
use Surface\Contracts\HumanInput\InputChannel as InputChannelContract;
use Surface\Contracts\HumanInput\InputContext;
use Surface\Contracts\HumanInput\InputStage;
use Surface\HumanInput\Devices\GameController;
use Surface\HumanInput\Devices\GamePad;
use Surface\HumanInput\HumanInputManager;
use Surface\HumanInput\InputBackend;
use Surface\HumanInput\PadSource;

/**
 * A backend as a package would declare it: where it runs (it refuses elsewhere, as it is made) and
 * which presentations it reaches.
 */
final class FakeBackend extends InputBackend
{
    public int $polls = 0;

    private bool $connected = false;

    /**
     * @param list<string> $runsOn the OSes it can run on
     * @param Closure(InputContext): bool $reaches
     */
    public function __construct(
        private readonly string $name,
        string $os,
        array $runsOn,
        private readonly Closure $reaches,
    ) {
        if (! in_array($os, $runsOn, true)) {
            throw new HumanInputException("it does not run on {$os}.");
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function serves(InputContext $context): bool
    {
        return ($this->reaches)($context);
    }

    public function connect(): static
    {
        $this->connected = true;

        return $this;
    }

    public function disconnect(): void
    {
        $this->connected = false;
        $this->closeChannels();
    }

    public function connected(): bool
    {
        return $this->connected;
    }

    public function poll(): void
    {
        $this->polls++;
    }
}

final class FakePadSource extends PadSource
{
    private bool $connected = false;

    public function name(): string
    {
        return 'fakepads';
    }

    public function connect(): static
    {
        $this->connected = true;

        return $this;
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }

    public function connected(): bool
    {
        return $this->connected;
    }

    public function poll(): void {}

    public function plug(GamePad $pad): GamePad
    {
        $this->add($pad);

        return $pad;
    }

    public function unplug(string $id): void
    {
        $this->remove($id);
    }
}

/**
 * A manager with the four backends the table names, each declaring what its package will: AppKit
 * runs on macOS and reaches AppKit's stages; Raven runs anywhere and reaches Raven's; evdev runs on
 * Linux and reaches every stage; SDL3 reaches the stages drawn with its own engine, on Linux.
 *
 * @param array<string, mixed> $config
 * @param list<object> $mail what it sends
 */
function inputManager(array $config, string $os = 'mac', ?array &$mail = null): HumanInputManager
{
    $mail = [];
    $manager = new class($config) extends HumanInputManager {
        public function __construct(array $config)
        {
            $this->config = fakeConfig($config);
        }

        protected function callCustomCreator(string $driver): mixed
        {
            return $this->customCreators[$driver]();
        }

        protected function callPadCreator(string $name): Surface\HumanInput\PadSource
        {
            return $this->padCreators[$name]();
        }
    };

    return $manager
        ->mailTo(function (object $m) use (&$mail): void { $mail[] = $m; })
        ->extend('appkit', fn () => new FakeBackend('appkit', $os, ['mac'], fn (InputContext $c): bool => $c->toolkit === 'appkit'))
        ->extend('raven', fn () => new FakeBackend('raven', $os, ['mac', 'linux'], fn (InputContext $c): bool => $c->toolkit === 'raven'))
        ->extend('evdev', fn () => new FakeBackend('evdev', $os, ['linux'], fn (InputContext $c): bool => true))
        ->extend('sdl3', fn () => new FakeBackend('sdl3', $os, ['mac', 'linux'], fn (InputContext $c): bool => $c->engine === 'sdl3' && $c->os === 'linux'))
        ->extendPads('fakepads', fn () => new FakePadSource());
}

/** A stage that takes input, settled by what is presented on it. */
function inputStage(?Closure $channel = null): InputStage
{
    return new class($channel) implements InputStage {
        public int $settles = 0;

        public int $displays = 0;

        public function __construct(private readonly ?Closure $channel) {}

        public function pixelSize(): array
        {
            return [4, 4];
        }

        public function pixelScale(): float
        {
            return 1.0;
        }

        public function display(string $rgba8, int $width, int $height): void
        {
            $this->displays++;
        }

        public function nativeSurface(): ?object
        {
            return null;
        }

        public function input(): InputChannelContract
        {
            return ($this->channel)($this);
        }

        public function settleInput(): void
        {
            $this->settles++;
        }
    };
}

function plainStage(): ActorStage
{
    return inputStage();
}

function fakeController(string $id = 'pad-1'): GameController
{
    return new GameController($id, 'Fake Controller', [GamepadButton::SOUTH, GamepadButton::EAST, GamepadButton::START]);
}
