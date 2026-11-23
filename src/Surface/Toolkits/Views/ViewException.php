<?php

namespace Surface\Toolkits\Views;

use Surface\Toolkits\ToolkitException;

/**
 * A view that cannot be rendered as written: an unknown view or prop, duplicate keys among
 * siblings, a setting a primitive has no setter for.
 */
class ViewException extends ToolkitException {}
