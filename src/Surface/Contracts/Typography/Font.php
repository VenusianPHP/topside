<?php

namespace Surface\Contracts\Typography;

/** A face an engine can draw text with. */
interface Font
{
    public function type(): FontType;
}
