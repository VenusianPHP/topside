<?php

use Surface\VelvetGE\Drivers\Framebuffers\PHPLandFramebufferDriver;
use Venusian\Topside\Tests\Support\Framebuffers\FixtureRunner;

// A library driver may blend with its own arithmetic: the harness then holds it to a distance, not to the same bytes.

it('passes the exact driver with a tolerance', function (string $file) {
    FixtureRunner::run(new PHPLandFramebufferDriver(), $file, tolerance: 1);
})->with(fn () => array_slice(FixtureRunner::files(), 0, 3));

it('refuses bytes further off than the tolerance', function () {
    $fail = fn (int $tolerance) => (function () use ($tolerance) {
        $m = new ReflectionMethod(FixtureRunner::class, 'pixels');
        (new ReflectionProperty(FixtureRunner::class, 'tolerance'))->setValue(null, $tolerance);
        try {
            $m->invoke(null, '10ff', '12ff', 'probe');

            return false;
        } catch (Throwable) {
            return true;
        } finally {
            (new ReflectionProperty(FixtureRunner::class, 'tolerance'))->setValue(null, 0);
        }
    })();

    expect($fail(0))->toBeTrue()->and($fail(1))->toBeTrue()->and($fail(2))->toBeFalse();
});
