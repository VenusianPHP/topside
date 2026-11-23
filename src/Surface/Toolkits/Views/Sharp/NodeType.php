<?php

namespace Surface\Toolkits\Views\Sharp;

/**
 * @internal
 */
enum NodeType
{
    case TAG;
    case TEXT;
    case ECHO;
    case IF;
    /** One branch of an @if; an empty value is the @else. */
    case BRANCH;
    case LOOP;
    case PHP;
}
