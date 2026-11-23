<?php

namespace Surface\Toolkits\Menus;

use Surface\Toolkits\ToolkitException;

/**
 * A menu that cannot be what was written: an item that does nothing, two with one id or one
 * shortcut, a profile nobody defined.
 */
class MenuException extends ToolkitException {}
