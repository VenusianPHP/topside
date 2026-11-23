<?php

namespace Surface\Toolkits\Views\Sharp;

use ParseError;

/**
 * The PHP inside a template, read with PHP's own tokenizer: where it ends and whether it parses.
 *
 * @internal
 */
final class Php
{
    /**
     * Where $close ends the PHP that begins at $from: not inside a string, a comment or brackets.
     *
     * @param string $close '}}' or ')'
     */
    public static function end(string $source, int $from, string $close): ?int
    {
        for ($at = strpos($source, $close, $from); $at !== false; $at = strpos($source, $close, $at + 1)) {
            $tokens = token_get_all('<?php '.substr($source, $from, $at - $from).$close);
            array_shift($tokens);

            $tail = array_splice($tokens, -strlen($close));
            if ($tail === str_split($close) && self::balanced($tokens)) {
                return $at;
            }
        }

        return null;
    }

    /**
     * Why $code does not parse as a file's content after <?php, or null when it does.
     */
    public static function problem(string $code): ?ParseError
    {
        try {
            token_get_all('<?php '.$code, TOKEN_PARSE);

            return null;
        } catch (ParseError $e) {
            return $e;
        }
    }

    /**
     * The tokens of $code that are neither space nor comment.
     *
     * @return list<array{int, string, int}|string>
     */
    public static function tokens(string $code): array
    {
        return array_values(array_filter(
            array_slice(token_get_all('<?php '.$code), 1),
            static fn (array|string $token): bool => ! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
        ));
    }

    private static function balanced(array $tokens): bool
    {
        $depth = 0;
        foreach ($tokens as $token) {
            $text = is_array($token) ? $token[0] : $token;
            if (in_array($text, ['{', '(', '[', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES, T_ATTRIBUTE], true)) {
                $depth++;
            } elseif (in_array($text, ['}', ')', ']'], true)) {
                $depth--;
            }
        }

        return $depth === 0;
    }
}
