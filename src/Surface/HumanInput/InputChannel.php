<?php

namespace Surface\HumanInput;

use Closure;
use Surface\Contracts\Graphics\ActorStage;
use Surface\Contracts\HumanInput\HumanInputException;
use Surface\Contracts\HumanInput\InputChannel as InputChannelContract;
use Surface\Contracts\HumanInput\Key;
use Surface\Contracts\HumanInput\Mail\KeyChanged;
use Surface\Contracts\HumanInput\Mail\PointerButtonChanged;
use Surface\Contracts\HumanInput\Mail\PointerMoved;
use Surface\Contracts\HumanInput\Mail\PointerScrolled;
use Surface\Contracts\HumanInput\Mail\TextTyped;
use Surface\Contracts\HumanInput\Modifiers;
use Surface\Contracts\HumanInput\MouseButton;
use Surface\Contracts\HumanInput\ScrollPhase;
use Surface\HumanInput\Devices\ControllerView;
use Surface\HumanInput\Devices\GameController;
use Surface\HumanInput\Devices\GamePad;
use Surface\HumanInput\Devices\Keyboard;
use Surface\HumanInput\Devices\Mouse;
use Surface\HumanInput\Devices\PadView;

/**
 * One stage's input. Its backend reports what the library says through the record methods
 * (key(), text(), pointer(), button(), scroll(), ...): each updates the state the stage's frames
 * read and sends the change as mail. The pads are the process's, read through views that keep this
 * stage's edges.
 *
 * A backend whose library says more about a device than topside does gives the channel its own
 * device: override makeMouse() or makeKeyboard().
 */
class InputChannel implements InputChannelContract
{
    protected Keyboard $keyboard;

    protected Mouse $mouse;

    /** @var array<string, PadView> by pad id */
    protected array $views = [];

    protected bool $closed = false;

    /** Whether the pointer was placed yet: the first report is where it is, not a move. */
    protected bool $placed = false;

    /**
     * @param Closure(object): void $mail sends one piece of mail
     * @param Closure(): array<string, GamePad> $pads the process's connected pads
     */
    public function __construct(
        protected readonly ActorStage $stage,
        protected readonly Closure $mail,
        protected readonly Closure $pads,
    ) {
        $this->keyboard = $this->makeKeyboard();
        $this->mouse = $this->makeMouse();
    }

    protected function makeKeyboard(): Keyboard
    {
        return new Keyboard();
    }

    protected function makeMouse(): Mouse
    {
        return new Mouse();
    }

    // What the backend reports.

    public function key(Key $key, bool $down, ?int $at_ns = null): void
    {
        $was = $this->keyboard->isDown($key);
        $this->keyboard->update($key, $down, $at_ns);
        if ($was !== $down) {
            $this->send(new KeyChanged($this->stage, $key, $down, $this->keyboard->modifiers()));
        }
    }

    public function text(string $text): void
    {
        if ($text === '') {
            return;
        }
        $this->keyboard->appendText($text);
        $this->send(new TextTyped($this->stage, $text));
    }

    public function modifiers(Modifiers $modifiers): void
    {
        $this->keyboard->setModifiers($modifiers);
    }

    /** Where the pointer is, in the stage's pixels: the motion from the last report is added. */
    public function pointer(float $x, float $y, bool $inside = true): void
    {
        $mouse = $this->mouse;
        if ($this->placed && $x === $mouse->x() && $y === $mouse->y() && $inside === $mouse->inside()) {
            return;                                              // a report of where it already is
        }
        // a move counts when either end of it is over the stage
        if ($this->placed && ($mouse->inside() || $inside)) {
            $mouse->addMotion($x - $mouse->x(), $y - $mouse->y());
        }
        $mouse->moveTo($x, $y, $inside);
        $this->placed = true;
        $this->send(new PointerMoved($this->stage, $x, $y, $inside));
    }

    /** Motion with no position: a device that reports only how far it moved. */
    public function motion(float $dx, float $dy): void
    {
        $this->mouse->addMotion($dx, $dy);
    }

    public function button(MouseButton $button, bool $down, ?int $at_ns = null): void
    {
        $was = $this->mouse->isDown($button);
        $this->mouse->update($button, $down, $at_ns);
        if ($was !== $down) {
            $this->send(new PointerButtonChanged($this->stage, $button, $down, $this->mouse->x(), $this->mouse->y(), $this->keyboard->modifiers()));
        }
    }

    public function scroll(float $dx, float $dy, bool $precise, bool $inverted = false, ScrollPhase $phase = ScrollPhase::NONE): void
    {
        $this->mouse->addScroll($dx, $dy, $precise, $inverted, $phase);
        $this->send(new PointerScrolled($this->stage, $dx, $dy, $precise, $inverted, $phase));
    }

    /** The stage lost the keyboard: every held key goes up, each with its release, and so do the modifiers. */
    public function releaseKeys(?int $at_ns = null): void
    {
        foreach ($this->keyboard->downKeys() as $key) {
            $this->key($key, false, $at_ns);
        }
        $this->keyboard->releaseAll($at_ns);
    }

    /** The application lost focus: every held key and button goes up, each with its release. */
    public function releaseAll(?int $at_ns = null): void
    {
        $this->releaseKeys($at_ns);
        foreach (MouseButton::cases() as $button) {
            $this->button($button, false, $at_ns);
        }
    }

    // What the stage reads.

    public function stage(): ActorStage
    {
        return $this->stage;
    }

    public function keyboard(): Keyboard
    {
        $this->open();

        return $this->keyboard;
    }

    public function mouse(): Mouse
    {
        $this->open();

        return $this->mouse;
    }

    public function gamePads(): array
    {
        $this->open();
        $pads = ($this->pads)();
        // a pad that went takes its view with it; one that came gets a new view
        $this->views = array_intersect_key($this->views, $pads);
        foreach ($pads as $id => $pad) {
            $this->views[$id] ??= $pad instanceof GameController ? new ControllerView($pad) : new PadView($pad);
        }

        return $this->views;
    }

    public function gameControllers(): array
    {
        return array_filter($this->gamePads(), fn (PadView $view): bool => $view instanceof ControllerView);
    }

    public function settle(): void
    {
        $this->keyboard->settle();
        $this->mouse->settle();
        foreach ($this->views as $view) {
            $view->settle();
        }
    }

    public function close(): void
    {
        $this->closed = true;
    }

    public function closed(): bool
    {
        return $this->closed;
    }

    protected function send(object $mail): void
    {
        if (! $this->closed) {
            ($this->mail)($mail);
        }
    }

    /** @throws HumanInputException */
    protected function open(): void
    {
        if ($this->closed) {
            throw HumanInputException::channelClosed();
        }
    }
}
