<?php

namespace VelvetGE\Contracts\Framebuffers;

use Surface\Contracts\Rendering\deprecated\Framebuffers\InstanceThatManagesAFramebuffer;
use Surface\NutsAndBolts\GradientSpread;

/**
 * A framebuffer that paints gradients into its own pixels. Separate from CompositingFramebuffer
 * because some libraries composite but have no gradient (SDL3): a module states what it does by
 * the contracts it implements, and an application that needs gradients swaps in one that does.
 */
interface GradientFramebuffer extends InstanceThatManagesAFramebuffer
{
    /**
     * Paints a gradient through spans: each covered pixel takes the ramp colour of its position, its alpha scaled by the
     * span's coverage, blended over what is there. The position of a pixel centre (x + ½, y + ½) goes through $inverse
     * (a, b, c, d, e, f: u = a·x + c·y + e, v = b·x + d·y + f); a linear gradient's t is u, a radial one's is hypot(u, v);
     * $spread folds t into 0..1; the ramp entry is floor(t × 255 + ½). Spans must lie inside the surface.
     *
     * @param  string  $spans  Packed spans, in rows ascending, x ascending without overlap within a row.
     * @param  string  $ramp  256 colours of 4 bytes, RGBA, straight alpha.
     * @param  string  $kind  'linear' or 'radial'.
     * @param  array{float, float, float, float, float, float}  $inverse
     */
    public function paintGradient(string $spans, string $ramp, string $kind, GradientSpread $spread, array $inverse): static;
}
