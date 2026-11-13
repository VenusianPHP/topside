<?php

declare(strict_types=1);

use Surface\Contracts\Rendering\Framebuffers\FramebufferException;
use Surface\VelvetGE\Drivers\Framebuffers\CLandFramebufferDriver;

/*
 * An ext-fb built before paintGradient() and filter() reports 0.11.1 too. The extended driver names the methods it needs and
 * is not available without them: 'auto' then picks native, and 'extended' says to rebuild.
 */

it('lists the methods a build lacks', function (): void {
    expect(CLandFramebufferDriver::missingMethods(stdClass::class))->toBe(['paintGradient', 'filter'])
        ->and(CLandFramebufferDriver::missingMethods(new class { public function filter(): void {} }::class))->toBe(['paintGradient']);
});

it('is available only with every method on the loaded build', function (): void {
    expect(CLandFramebufferDriver::available())->toBe(class_exists(FbBuffer::class, false) && CLandFramebufferDriver::missingMethods() === [] && version_compare((string) phpversion('fb'), CLandFramebufferDriver::MINIMUM, '>='));
});

it('tells a stale build to be rebuilt', function (): void {
    $e = FramebufferException::extensionStale('fb', '0.11.1', ['paintGradient', 'filter']);

    expect($e->getMessage())->toContain('0.11.1 is loaded but is an old build without paintGradient and filter()')->toContain('rebuild ext-fb');
});
