<?php

namespace Surface\Toolkits\Views\Sharp;

/**
 * Writes the compiled class so that code from line N of the template is on line N of the
 * compiled file: a runtime error's line is the line to look at.
 *
 * @internal
 */
final class CodeWriter
{
    private string $code = '';

    private int $line = 1;

    public function add(string $code): void
    {
        $this->code .= $code;
        $this->line += substr_count($code, "\n");
    }

    public function at(int $line, string $code): void
    {
        if ($this->line < $line) {
            $this->add(str_repeat("\n", $line - $this->line));
        }
        $this->add($code.' ');
    }

    public function code(): string
    {
        return $this->code;
    }
}
