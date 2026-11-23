<?php

namespace Surface\Toolkits\Views\Sharp;

/**
 * One piece of a template, as the Lexer read it.
 *
 * @internal
 */
final class Node
{
    /**
     * @param string $value the tag's name, the text, the expression, or the PHP
     * @param list<Attribute> $attributes a tag's
     * @param list<Node> $children a tag's, a branch's or a loop's content; an @if's are its branches
     */
    public function __construct(
        public readonly NodeType $type,
        public readonly int $line,
        public readonly string $value = '',
        public readonly array $attributes = [],
        public readonly array $children = [],
    ) {}
}
