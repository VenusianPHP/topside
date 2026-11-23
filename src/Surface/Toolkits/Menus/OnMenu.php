<?php

namespace Surface\Toolkits\Menus;

use Attribute;

/**
 * On a method of a component or of a Sharp @script: it runs when menu mail with this path
 * arrives while the view is on screen, and the view renders again after it.
 *
 *     #[OnMenu('file.save')]
 *     public function save(MenuMail $mail): void {}    // or with no argument
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class OnMenu
{
    public function __construct(
        public string $path,
    ) {}
}
