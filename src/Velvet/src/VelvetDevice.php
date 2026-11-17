<?php

namespace VelvetGE;

use ReflectionException;
use Throwable;
use Surface\Graphics\GfxDevice;
use Surface\Contracts\Graphics\Feature;
use Surface\Contracts\Graphics\Backend;
use Surface\Contracts\Graphics\CastsRays;
use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Contracts\Graphics\ActorStage;
use Surface\Contracts\Graphics\GraphicsException;
use Surface\Contracts\Rendering\Painting\PaintDriver;
use Surface\Contracts\Rendering\Painting\PaintTarget;
use VelvetGE\Contracts\Framebuffers\EffectsFramebuffer;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Framebuffers\FramebufferException;
use VelvetGE\Contracts\Framebuffers\GradientFramebuffer;
use VelvetGE\Contracts\Framebuffers\ScrollingFramebuffer;
use VelvetGE\Contracts\Framebuffers\CompositingFramebuffer;
use Surface\Contracts\Rendering\Framebuffers\InstanceThatManagesAFramebuffer;

/**
 * Velvet as a graphics engine: the way in from Gfx::engine('velvet').
 *
 * Velvet is made of modules. When the engine is made it reads config/velvet.php, takes the
 * framebuffer and painting modules named there from Velvet's managers, and makes one small
 * framebuffer to see what the pair can do. A module that is not installed, or a framebuffer the
 * painter cannot draw into, fails here and not at the first frame.
 */
class VelvetDevice extends GfxDevice implements CastsRays
{
    /**
     * Velvet's framebuffer module: makes the pixels.
     */
    protected object $framebuffers;

    /**
     * Velvet's painting module: makes the painter that draws into them.
     */
    protected PaintDriver $painters;

    /**
     * @var array<string, bool> Feature value => whether the modules in use have it
     */
    protected array $features;

    /**
     * The host format surfaces are made in when none is asked for: the first of HOST_FORMATS the
     * framebuffer module holds.
     */
    protected FormatSpec $format;

    /** The host formats a surface may be made in, best first: a stage takes RGBA8, so RGBA8 needs no conversion to show. */
    protected const array HOST_FORMATS = ['rgba8', 'bgra8'];

    /**
     * @throws VelvetGEException|ReflectionException When the framebuffer module in use cannot be painted into.
     */
    public function __construct(Backend $backend)
    {
        parent::__construct($backend);

        $this->framebuffers = app('velvet.framebuffers')->driver();
        $this->painters = app('velvet.painters')->driver();

        [$this->format, $probe] = $this->hostFormat();
        $this->paintable($probe);

        $this->features = [
            Feature::GRADIENTS->value => $probe instanceof GradientFramebuffer,
            Feature::EFFECTS->value => $probe instanceof EffectsFramebuffer,
            Feature::SCROLLING->value => $probe instanceof ScrollingFramebuffer,
            Feature::RAY_CASTING->value => $this->resolves('velvet.raycasters'),
        ];
    }

    public function name(): string
    {
        return 'velvet';
    }

    /** @return array<string, string> part => the module named for it in config/velvet.php */
    public function modules(): array
    {
        return [
            'framebuffer' => (string) app('velvet.framebuffers')->getDefaultDriver(),
            'rasterizer' => (string) app('velvet.rasterizers')->getDefaultDriver(),
            'painter' => (string) app('velvet.painters')->getDefaultDriver(),
            'raycaster' => (string) app('velvet.raycasters')->getDefaultDriver(),
        ];
    }

    public function has(Feature $feature): bool
    {
        return $this->features[$feature->value];
    }

    /**
     * A surface the size of the stage, shown there by present().
     *
     * @throws GraphicsException When the stage has not been laid out yet.
     */
    public function surface(ActorStage $stage, ?FormatSpec $format = null): VelvetSurface
    {
        [$width, $height] = $stage->pixelSize();
        if ($width < 1 || $height < 1) {
            throw GraphicsException::stageHasNoSize();
        }

        return $this->make($width, $height, $format, $stage);
    }

    /**
     * A surface with no stage: drawn into and read back, or drawn onto another surface as an image.
     *
     * @throws GraphicsException When a side is less than 1.
     */
    public function offscreen(int $width, int $height, ?FormatSpec $format = null): VelvetSurface
    {
        if ($width < 1 || $height < 1) {
            throw GraphicsException::badSize($width, $height);
        }

        return $this->make($width, $height, $format, null);
    }

    /**
     * A raycaster over $grid, from the raycasting module config/velvet.php names. With no such
     * module installed, Velvet's raycasting manager refuses here.
     *
     * @throws ReflectionException
     */
    public function raycaster(Grid $grid): VelvetRaycaster
    {
        return new VelvetRaycaster(app('velvet.raycasters')->driver()->caster($grid));
    }

    protected function make(int $width, int $height, ?FormatSpec $format, ?ActorStage $stage): VelvetSurface
    {
        $framebuffer = $this->paintable($this->framebuffers->make($format ?? $this->format, $width, $height));

        return new VelvetSurface($this, $framebuffer, $this->painters->painter($framebuffer), $stage);
    }

    /**
     * The first host format the framebuffer module holds, and the small framebuffer it made in it.
     *
     * @return array{FormatSpec, InstanceThatManagesAFramebuffer}
     *
     * @throws VelvetGEException|ReflectionException When it holds none of them.
     */
    protected function hostFormat(): array
    {
        $refused = [];
        foreach (self::HOST_FORMATS as $name) {
            try {
                return [$format = FormatSpec::named($name), $this->framebuffers->make($format, 1, 1)];
            } catch (FramebufferException $e) {
                $refused[] = "{$name}: {$e->getMessage()}";
            }
        }
        $module = app('velvet.framebuffers')->getDefaultDriver();

        throw new VelvetGEException("Velvet's framebuffer module [{$module}] holds none of the host formats: ".implode(' ', $refused));
    }

    /**
     * @throws VelvetGEException|ReflectionException
     */
    protected function paintable(InstanceThatManagesAFramebuffer $framebuffer): CompositingFramebuffer&PaintTarget
    {
        if (! $framebuffer instanceof CompositingFramebuffer || ! $framebuffer instanceof PaintTarget) {
            $name = app('velvet.framebuffers')->getDefaultDriver();

            throw new VelvetGEException("Velvet's framebuffer module [{$name}] holds pixels but cannot be painted into: name one that can in config/velvet.php.");
        }

        return $framebuffer;
    }

    /** Whether the module named for a manager is installed and loads. */
    protected function resolves(string $manager): bool
    {
        try {
            app($manager)->driver();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
