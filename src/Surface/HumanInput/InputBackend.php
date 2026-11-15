<?php

namespace Surface\HumanInput;

use Closure;
use Surface\Contracts\Graphics\ActorStage;
use Surface\Contracts\HumanInput\InputBackend as InputBackendContract;
use Surface\HumanInput\Devices\GamePad;

/**
 * What every backend does the same way: one channel a stage, kept until the backend disconnects.
 * A backend supplies serves(), how it listens (connect(), disconnect(), poll()), and, when its
 * library says more about a device, its own channel through makeChannel().
 */
abstract class InputBackend implements InputBackendContract
{
    /** @var array<int, InputChannel> by spl_object_id of the stage */
    protected array $channels = [];

    /** @var (Closure(object): void)|null */
    protected ?Closure $mail = null;

    /** @var (Closure(): array<string, GamePad>)|null */
    protected ?Closure $pads = null;

    /**
     * Where its channels send mail and find pads. The manager calls it when it resolves the backend.
     *
     * @param Closure(object): void $mail
     * @param Closure(): array<string, GamePad> $pads
     * @internal
     */
    public function route(Closure $mail, Closure $pads): void
    {
        $this->mail = $mail;
        $this->pads = $pads;
    }

    public function open(ActorStage $stage): InputChannel
    {
        $id = spl_object_id($stage);
        if (isset($this->channels[$id]) && $this->channels[$id]->closed()) {
            unset($this->channels[$id]);
        }

        return $this->channels[$id] ??= $this->makeChannel($stage);
    }

    /** @return list<InputChannel> the channels open now */
    public function channels(): array
    {
        return array_values(array_filter($this->channels, fn (InputChannel $c): bool => ! $c->closed()));
    }

    /** The open channel of a stage, or null: what a backend reports to as its library speaks of the stage. */
    public function channelOf(ActorStage $stage): ?InputChannel
    {
        $channel = $this->channels[spl_object_id($stage)] ?? null;

        return $channel?->closed() === false ? $channel : null;
    }

    protected function makeChannel(ActorStage $stage): InputChannel
    {
        $this->mail ??= static fn (object $mail) => null;
        $this->pads ??= static fn (): array => [];

        return new InputChannel($stage, $this->mail, $this->pads);
    }

    /** Closes every channel: a backend's disconnect() calls it. */
    protected function closeChannels(): void
    {
        foreach ($this->channels as $channel) {
            $channel->close();
        }
        $this->channels = [];
    }
}
