<?php

namespace Surface\VelvetGE;

use Surface\Contracts\Graphics\Backend;
use Surface\Contracts\Graphics\GraphicsException;
use Surface\Contracts\Graphics\Resource;
use Surface\Contracts\Rendering\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\Rasterization\Edges;
use Surface\Contracts\Rendering\Raycasting\Grid;
use Surface\Graphics\GEFramebuffer;
use Surface\Graphics\GEPainter;
use Surface\Graphics\GERasterizer;
use Surface\Graphics\GERaycaster;
use Surface\Graphics\GraphicsEngine;
use Surface\Rendering\Framebuffers\FramebufferLibraryManager;
use Surface\Rendering\Painters\PaintingLibraryManager;
use Surface\Rendering\Rasterization\RasterizationLibraryManager;
use Surface\Rendering\Raycasting\RaycastingLibraryManager;

/**
 * VelvetGE as a graphics engine. On the CPU it is built on Rendering: whichever framebuffer,
 * rasterization, painting and raycasting drivers the application has. Its GPU backend is not
 * built yet.
 */
class VelvetDevice extends GraphicsEngine
{
    /**
     * @param RaycastingLibraryManager|null $raycasters null: raycasting is off (rendering.raycaster.enabled)
     * @param array{framebuffer?: ?string, rasterizer?: ?string, painter?: ?string, raycaster?: ?string} $drivers
     *        the driver Velvet uses for each part (config/velvet.php's cpu); a part missing or null uses Rendering's default
     *
     * @throws GraphicsException For the GPU backend.
     */
    public function __construct(
        Backend $backend,
        protected readonly FramebufferLibraryManager $framebuffers,
        protected readonly RasterizationLibraryManager $rasterization,
        protected readonly PaintingLibraryManager $painters,
        protected readonly ?RaycastingLibraryManager $raycasters = null,
        protected readonly array $drivers = [],
    ) {
        if ($backend !== Backend::CPU) {
            throw GraphicsException::backendNotReady('velvet', $backend);
        }
        parent::__construct($backend);
    }

    public function name(): string
    {
        return 'velvet';
    }

    /** The Rendering driver behind each part, as 'auto' or the name given resolved it. */
    public function modules(): array
    {
        $modules = [
            'framebuffer' => $this->framebuffers->driver($this->drivers['framebuffer'] ?? null)->driver(),
            'rasterizer' => $this->rasterization->driver($this->drivers['rasterizer'] ?? null)->driver(),
            'painter' => $this->painters->driver($this->drivers['painter'] ?? null)->driver(),
        ];
        if (! is_null($this->raycasters)) {
            $modules['raycaster'] = $this->raycasters->driver($this->drivers['raycaster'] ?? null)->driver();
        }

        return $modules;
    }

    public function supports(Resource $resource): bool
    {
        return $resource !== Resource::RAYCASTER || ! is_null($this->raycasters);
    }

    /**
     * @param FormatSpec|null $format null: RGBA8
     *
     * @throws GraphicsException When a side is under 1.
     */
    public function framebuffer(int $width, int $height, ?FormatSpec $format = null): GEFramebuffer
    {
        if ($width < 1 || $height < 1) {
            throw GraphicsException::badSize($width, $height);
        }

        // 'dirty' keeps what was drawn and knows where it changed, which a painter's partial frames need
        return new VelvetGEFramebuffer($this, $this->framebuffers->driver($this->drivers['framebuffer'] ?? null)->dirty($format ?? FormatSpec::rgba8(), $width, $height));
    }

    public function rasterizer(Edges $edges = Edges::ANTIALIASED): GERasterizer
    {
        return new VelvetGERasterizer($this, $this->rasterization->driver($this->drivers['rasterizer'] ?? null), $edges);
    }

    public function raycaster(Grid $grid): GERaycaster
    {
        if (is_null($this->raycasters)) {
            throw GraphicsException::unsupported('velvet', Resource::RAYCASTER, "Turn raycasting on: config/rendering.php's raycaster.enabled (RENDERING_RAYCASTER=true).");
        }

        return new VelvetGERaycaster($this, $this->raycasters->driver($this->drivers['raycaster'] ?? null)->caster($grid));
    }

    public function painter(GEFramebuffer $onto): GEPainter
    {
        if ($onto->engine() !== $this || ! $onto instanceof VelvetGEFramebuffer) {
            throw GraphicsException::foreignResource('velvet', $onto->engine()->name());
        }

        return new VelvetGEPainter($this, $onto, $this->painters->driver($this->drivers['painter'] ?? null)->painter($onto->inner()));
    }
}
