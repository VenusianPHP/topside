<?php

namespace Surface\Contracts\Toolkits\Menus;

interface MenuProfile
{
    public static function parse(string $name, array $nodes): MenuProfile;
}