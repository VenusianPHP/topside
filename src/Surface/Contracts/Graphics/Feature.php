<?php

namespace Surface\Contracts\Graphics;

enum Feature: string
{
    case GRADIENTS  = 'gradients';   // fill() and stroke() with a Gradient paint
    case EFFECTS    = 'effects';     // layer() filters and backdrops, glass(), shadow()
    case SCROLLING  = 'scrolling';   // scroll() moves pixels already drawn
    case RAY_CASTING = 'ray-casting';  // the engine can paint a view of a grid
}
