<?php

namespace Surface\Contracts\Rendering\Rasterization;

/**
 * How a shape's boundary meets the pixel grid. Asked of a rasterizer, it is a preference: a
 * scanner draws the edges its library has, and Scanner::edges() says which those are. A frame
 * that cannot hold partial coverage takes it from 128 up when the coverage lands.
 */
enum Edges: string
{
    /** Whole pixels only: coverage 255 or nothing. Which pixels are in is the scanner's own rule. */
    case HARD = 'hard';

    /** A pixel's coverage is how much of it the shape covers: 1..255. */
    case ANTIALIASED = 'antialiased';
}
