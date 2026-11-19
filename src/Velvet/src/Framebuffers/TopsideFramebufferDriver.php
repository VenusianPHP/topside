<?php

namespace VelvetGE\Framebuffers;

use ReflectionException;
use Surface\Contracts\Rendering\deprecated\Framebuffers\DamageTrackingFramebuffer;
use Surface\Contracts\Rendering\deprecated\Framebuffers\FormatSpec;
use Surface\Contracts\Rendering\deprecated\Framebuffers\InstanceThatManagesAFramebuffer;
use Surface\Rendering\deprecated\Framebuffers\FramebufferDriver;
use VelvetGE\Contracts\Framebuffers\ePaperFramebuffer;
use VelvetGE\Contracts\Framebuffers\PagedFramebuffer;
use VelvetGE\Contracts\Framebuffers\RingFramebuffer;

class TopsideFramebufferDriver extends FramebufferDriver
{
    protected ?string $active_driver = null;

    public function __construct()
    {
        $this->active_driver = config('velvet.cpu.framebuffers.default');
    }
    /**
     * The framebuffer Velvet's active module makes.
     *
     * @throws ReflectionException
     */
    public function make(FormatSpec $format, int $width, int $height): InstanceThatManagesAFramebuffer
    {
        /** @var VelvetFramebufferManager $mgr */
        $mgr = app('velvet.framebuffers');
        return $mgr->driver($this->active_driver)->make($format, $width, $height);
    }

    /**
     * @throws ReflectionException
     */
    public function full(FormatSpec $format, int $width, int $height): InstanceThatManagesAFramebuffer
    {
        /** @var VelvetFramebufferManager $mgr */
        $mgr = app('velvet.framebuffers');
        return $mgr->driver($this->active_driver)->full($format, $width, $height);
    }

    /**
     * @throws ReflectionException
     */
    public function dirty(FormatSpec $format, int $width, int $height): DamageTrackingFramebuffer
    {
        /** @var VelvetFramebufferManager $mgr */
        $mgr = app('velvet.framebuffers');
        return $mgr->driver($this->active_driver)->dirty($format, $width, $height);
    }

    /**
     * @throws ReflectionException
     */
    public function epaper(FormatSpec $format, int $width, int $height): ePaperFramebuffer
    {
        /** @var VelvetFramebufferManager $mgr */
        $mgr = app('velvet.framebuffers');
        return $mgr->driver($this->active_driver)->epaper($format, $width, $height);
    }

    /**
     * @throws ReflectionException
     */
    public function paged(FormatSpec $format, int $width, int $height, int $page_rows): PagedFramebuffer
    {
        /** @var VelvetFramebufferManager $mgr */
        $mgr = app('velvet.framebuffers');
        return $mgr->driver($this->active_driver)->paged($format, $width, $height, $page_rows);
    }

    /**
     * @throws ReflectionException
     */
    public function ring(FormatSpec $format, int $width, int $height, int $frames): RingFramebuffer
    {
        /** @var VelvetFramebufferManager $mgr */
        $mgr = app('velvet.framebuffers');
        return $mgr->driver($this->active_driver)->ring($format, $width, $height, $frames);
    }

    /**
     * @throws ReflectionException
     */
    public function driver(): string
    {
        return 'velvet';
    }
}