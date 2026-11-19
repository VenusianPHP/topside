<?php

namespace VelvetGE;

use Closure;
use Surface\Contracts\Graphics\ActorStage;
use Surface\Contracts\Graphics\DrawingSurface;
use Surface\Contracts\Rendering\Drawer;
use Surface\Contracts\Rendering\Frame;
use Surface\Contracts\Rendering\FrameProvider;
use Surface\Contracts\Rendering\Recording\Recorder;
use Surface\Rendering\Region;
use Throwable;

/**
 * One stage's frames, drawn the Velvet way: a frame of commands is recorded, the recorder says where it differs from
 * the frame before, and the drawer draws those regions only. A command the drawer's library cannot draw as it is goes
 * to it in a simpler form the recorder lowers it to, when the drawer takes one. The frame's memory is made at the
 * stage's size, and made again when that size changes; a new frame is drawn whole.
 */
final class VelvetSurface implements DrawingSurface
{
    /** The simpler forms a command the drawer does not take is offered in, simplest last. */
    private const array FORMS = ['polygon', 'trapezoids', 'triangles'];

    private ?Frame $frame = null;

    /** @var list<Region> drawn since the last present */
    private array $pending = [];

    public function __construct(
        private readonly FrameProvider $frames,
        private readonly Drawer $drawer,
        private readonly Recorder $recorder,
        private readonly ?ActorStage $stage,
        private readonly int $width = 0,
        private readonly int $height = 0,
    ) {}

    public function size(): array
    {
        return $this->stage?->pixelSize() ?? [$this->width, $this->height];
    }

    public function draw(Closure $frame): static
    {
        [$width, $height] = $this->size();
        if ($width < 1 || $height < 1) {
            return $this;
        }
        $memory = $this->frame?->memory();
        $new = $memory === null || $memory->width !== $width || $memory->height !== $height;
        if ($new) {
            $this->frame = $this->frames->frame($width, $height);
            $memory = $this->frame->memory();
            $this->pending = [];
            if (! $this->drawer->drawsInto($memory)) {
                throw VelvetGEException::drawerCannotDrawInto($this->drawer->name(), $memory);
            }
        }

        $whole = new Region(0, 0, $width, $height);
        $this->recorder->begin($width, $height, $whole);
        try {
            $frame(new VelvetCanvas($this->recorder, $width, $height));
        } catch (Throwable $thrown) {
            $this->recorder->abandon();
            throw $thrown;
        }
        $commands = $this->recorder->finish();

        // where the frame changed is the recorder's, from the commands as recorded
        $rects = $this->recorder->changed($width, $height, 1, 1, $width, $height, $new ? [0, 0, $width, $height] : []);
        if ($rects === []) {
            return $this;
        }

        // one draw, clipped to the box around every changed region: what is unchanged inside it is drawn again from
        // the same commands, so its bytes are the same, and the stage is still told only the regions that changed
        $regions = [];
        [$x0, $y0, $x1, $y1] = [$width, $height, 0, 0];
        for ($i = 0, $n = count($rects); $i < $n; $i += 4) {
            $regions[] = new Region($rects[$i], $rects[$i + 1], $rects[$i + 2], $rects[$i + 3]);
            $x0 = min($x0, $rects[$i]);
            $y0 = min($y0, $rects[$i + 1]);
            $x1 = max($x1, $rects[$i] + $rects[$i + 2]);
            $y1 = max($y1, $rects[$i + 1] + $rects[$i + 3]);
        }
        $this->drawer->draw($this->drawable($commands), $memory, new Region($x0, $y0, $x1 - $x0, $y1 - $y0));
        $this->frame->wrote(...$regions);
        array_push($this->pending, ...$regions);

        return $this;
    }

    /**
     * The commands as the drawer takes them: each it does not take as it is, in the first simpler form the recorder
     * gives that it does take (a polygon, then trapezoids, then triangles), each form lowered from the one before;
     * one it takes in no form is left out.
     *
     * @param list<array> $commands
     * @return list<array>
     */
    private function drawable(array $commands): array
    {
        $drawable = [];
        foreach ($commands as $command) {
            if ($this->drawer->takes($command)) {
                $drawable[] = $command;

                continue;
            }
            $lowered = $command;
            foreach (self::FORMS as $form) {
                $lowered = $this->recorder->lower($lowered, $form);
                if ($lowered === null) {
                    break;
                }
                if ($this->drawer->takes($lowered)) {
                    $drawable[] = $lowered;

                    break;
                }
            }
        }

        return $drawable;
    }

    public function present(): static
    {
        $this->stage ?? throw VelvetGEException::noStage('velvet');
        if ($this->frame !== null && $this->pending !== []) {
            $this->stage->present($this->frame->memory(), ...$this->pending);
            $this->pending = [];
        }

        return $this;
    }
}
