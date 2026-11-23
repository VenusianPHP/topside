<?php

namespace Surface\Toolkits\Views\Sharp;

/**
 * A sharp file taken apart: the @script (its leading use lines are the file's imports), the
 * @template, and the @menu when it has one.
 *
 * @internal
 */
final readonly class Source
{
    private function __construct(
        public string $file,
        public string $imports,
        /** @var list<string> the short names the imports take, lowercase */
        public array $aliases,
        public string $script,
        /** The line $script begins on. */
        public int $scriptLine,
        public string $template,
        /** The line $template begins on. */
        public int $templateLine,
        public ?string $menu = null,
        /** The line $menu begins on. */
        public int $menuLine = 1,
        /** @menu replace: the block is the whole bar. */
        public bool $menuReplaces = false,
    ) {}

    /**
     * @throws SharpCompileError
     */
    public static function read(string $file, string $content): self
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));
        $sections = [];
        $open = null;
        $replaces = false;

        foreach ($lines as $index => $line) {
            $number = $index + 1;
            $marker = trim($line);

            if (! is_null($open)) {
                if ($marker === "@end{$open}") {
                    $open = null;
                } elseif (in_array($marker, ['@script', '@template', '@style', '@menu', '@menu replace'], true)) {
                    throw new SharpCompileError("{$marker} inside @{$open}: close that with @end{$open} first", $file, $number);
                } else {
                    $sections[$open][1][] = $line;
                }
                continue;
            }

            if (in_array($marker, ['@script', '@template', '@menu', '@menu replace'], true)) {
                $open = strtok(substr($marker, 1), ' ');
                $replaces = $replaces || $marker === '@menu replace';
                if (isset($sections[$open])) {
                    throw new SharpCompileError("A second @{$open}: a file has one", $file, $number);
                }
                $sections[$open] = [$number + 1, []];
            } elseif ($marker === '@style') {
                throw new SharpCompileError('@style is not supported yet: style elements with their attributes (background, font, cornerRadius)', $file, $number);
            } elseif (! in_array($marker, ['', '<?php', '?>'], true) && ! preg_match('/^(\{\{--.*--\}\}|\/\/.*)$/D', $marker)) {
                throw new SharpCompileError("'{$marker}' is outside @script, @template and @menu", $file, $number);
            }
        }

        if (! is_null($open)) {
            throw new SharpCompileError("@{$open} has no @end{$open}", $file, $sections[$open][0] - 1);
        }
        if (! isset($sections['template'])) {
            throw new SharpCompileError('A sharp file needs @template ... @endtemplate', $file, 1);
        }

        [$script_line, $script] = $sections['script'] ?? [1, []];
        [$template_line, $template] = $sections['template'];
        $script = implode("\n", $script);
        [$menu_line, $menu] = $sections['menu'] ?? [1, null];

        if ($script !== '' && preg_match('/<\?php|\?>/', $script)) {
            throw new SharpCompileError('@script is PHP already: leave the PHP tags out', $file, $script_line);
        }
        [$imports, $aliases, $script] = self::takeImports($script);

        return new self($file, $imports, $aliases, $script, $script_line, implode("\n", $template), $template_line,
            is_null($menu) ? null : implode("\n", $menu), $menu_line, $replaces);
    }

    /**
     * The use statements before the script's first member, which become the file's imports;
     * their lines are left blank so every other line keeps its number.
     *
     * @return array{string, list<string>, string}
     */
    private static function takeImports(string $script): array
    {
        $tokens = array_slice(token_get_all('<?php '.$script), 1);
        $imports = '';
        $aliases = [];
        $body = '';
        $importing = false;
        $name = '';

        foreach ($tokens as $index => $token) {
            [$id, $text] = is_array($token) ? $token : [$token, $token];
            $blank = in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);

            if (! $importing) {
                if ($blank) {
                    $body .= $text;
                    continue;
                }
                if ($id !== T_USE) {
                    return [$imports, $aliases, $body.implode('', array_map(
                        static fn (array|string $rest): string => is_array($rest) ? $rest[1] : $rest,
                        array_slice($tokens, $index),
                    ))];
                }
                $importing = true;
            }

            $body .= str_repeat("\n", substr_count($text, "\n"));
            $imports .= $blank ? ' ' : $text;

            if (in_array($id, [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $name = $text;
            } elseif (in_array($id, [',', ';', '}'], true) && $name !== '') {
                $aliases[] = strtolower(substr(strrchr('\\'.$name, '\\'), 1));
                $name = '';
            }
            if ($id === ';') {
                $importing = false;
            }
        }

        return [$imports, $aliases, $body];
    }
}
