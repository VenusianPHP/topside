<?php

namespace VelvetGE;

use Surface\Graphics\GraphicsEngine;

class VelvetDevice extends GraphicsEngine
{
    public function name(): string
    {
        return 'velvet';
    }
}