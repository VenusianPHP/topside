<?php

namespace Surface\HumanInput;

use Closure;
use Surface\Contracts\Graphics\ActorStage;
use Surface\Contracts\HumanInput\HumanInputException;
use Surface\Contracts\HumanInput\InputContext;
use Surface\Contracts\HumanInput\Mail\PadConnected;
use Surface\Contracts\HumanInput\Mail\PadDisconnected;
use Surface\HumanInput\Devices\GameController;
use Voyager\NutsAndBolts\Manager;

/**
 * The application's input: the one backend config's human-input.backend names, for keyboards and
 * pointers on stages, and the one pad source human-input.pads names, for the process's pads.
 * Topside names neither: each package registers its own, with extend() and extendPads().
 *
 * Whether a backend can have input for what the application is presented with is the backend's
 * own answer: it refuses by throwing as it is made, when it cannot run here, and says through
 * serves() whether it reaches that toolkit's stages, drawn with that engine, on that OS.
 */
class HumanInputManager extends Manager
{
    /** @var array<string, Closure> pad source creators, by name */
    protected array $padCreators = [];

    protected ?InputBackend $backend = null;

    protected ?PadSource $padSource = null;

    protected bool $padsResolved = false;

    /** @var array<string, string> the pads at the last poll: name by id */
    protected array $known = [];

    /** @var (Closure(object): void)|null */
    protected ?Closure $mail = null;

    public function getDefaultDriver(): ?string
    {
        return $this->config->get('human-input.backend');
    }

    /** Where mail goes. Unset, it is signalled. */
    public function mailTo(Closure $mail): static
    {
        $this->mail = $mail;

        return $this;
    }

    /** @param Closure(mixed): PadSource $creator */
    public function extendPads(string $name, Closure $creator): static
    {
        $this->padCreators[$name] = $creator;

        return $this;
    }

    /** @return list<string> the backends registered */
    public function backends(): array
    {
        return array_keys($this->customCreators);
    }

    /** @return list<string> the pad sources registered */
    public function padSources(): array
    {
        return array_keys($this->padCreators);
    }

    /**
     * Makes the configured backend and asks it whether it serves $context. Done once, as the
     * application boots: what comes after reads the backend it settled on.
     *
     * @throws HumanInputException When none is configured or registered by that name, it refuses to run here, or it does not serve $context.
     */
    public function resolve(InputContext $context): InputBackend
    {
        $name = $this->getDefaultDriver() ?? throw HumanInputException::noBackend($context);
        if (! isset($this->customCreators[$name])) {
            throw HumanInputException::unknownBackend($name, $this->backends());
        }

        try {
            $backend = $this->driver($name);
        } catch (HumanInputException $refusal) {
            throw HumanInputException::refused($name, $context, $refusal->getMessage());
        }

        if (! $backend->serves($context)) {
            throw HumanInputException::doesNotServe($name, $context);
        }

        $backend->route($this->sender(), fn (): array => $this->padSource?->pads() ?? []);

        return $this->backend = $backend;
    }

    /** The backend resolve() settled on, or null before it. */
    public function backend(): ?InputBackend
    {
        return $this->backend;
    }

    /**
     * The pad source human-input.pads names, made the first time it is asked for; null when it names none.
     *
     * @throws HumanInputException When it names one not registered, or that refuses to run here.
     */
    public function pads(): ?PadSource
    {
        if ($this->padsResolved) {
            return $this->padSource;
        }

        $name = $this->config->get('human-input.pads');
        if (! is_null($name)) {
            if (! isset($this->padCreators[$name])) {
                throw HumanInputException::unknownPadSource($name, $this->padSources());
            }
            try {
                $this->padSource = $this->callPadCreator($name);
            } catch (HumanInputException $refusal) {
                throw HumanInputException::padSourceRefused($name, $refusal->getMessage());
            }
        }
        $this->padsResolved = true;

        return $this->padSource;
    }

    /**
     * The stage's input, from the backend resolve() settled on.
     *
     * @throws HumanInputException Before resolve().
     */
    public function channel(ActorStage $stage): InputChannel
    {
        $backend = $this->backend ?? throw HumanInputException::notResolved();

        return $backend->connect()->open($stage);
    }

    /** Starts the backend and the pad source listening. */
    public function connect(): void
    {
        $this->backend?->connect();
        $this->pads()?->connect();
    }

    /** Applies what arrived since the last poll, and sends mail for the pads that came and went. */
    public function poll(): void
    {
        $this->backend?->poll();

        $source = $this->padSource;
        if (is_null($source)) {
            return;
        }
        $source->poll();

        $now = $source->pads();
        $send = $this->sender();
        foreach (array_diff_key($this->known, $now) as $id => $name) {
            $send(new PadDisconnected($id, $name));
        }
        foreach (array_diff_key($now, $this->known) as $id => $pad) {
            $send(new PadConnected($id, $pad->name, $pad instanceof GameController));
        }
        $this->known = array_map(fn ($pad): string => $pad->name, $now);
    }

    /** Stops listening, and closes every stage's input. */
    public function destroy(): void
    {
        $this->backend?->disconnect();
        $this->padSource?->disconnect();
        $this->known = [];
    }

    protected function callPadCreator(string $name): PadSource
    {
        return $this->padCreators[$name]($this->vessel);
    }

    /** @return Closure(object): void */
    protected function sender(): Closure
    {
        return fn (object $mail) => is_null($this->mail) ? signal($mail) : ($this->mail)($mail);
    }
}
