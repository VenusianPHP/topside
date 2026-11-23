<?php

namespace Surface\Toolkits\Views\Sharp;

/**
 * Reads a template into nodes. Markup with directives and {{ }} in it is not XML, so this is
 * its own reader; where PHP is embedded, PHP's tokenizer says where that ends.
 */
final class Lexer
{
    private const string DIRECTIVE = '/\G@(if|elseif|else|endif|foreach|endforeach|php|endphp)\b/';

    /** The template, each comment blanked but its newlines. */
    private string $text;

    private int $at = 0;

    /** @var list<int> where each line starts */
    private array $starts = [0];

    private readonly int $first;

    /**
     * @param string|null $text the block to read; the @template when null
     * @param int|null $first the line of the file it begins on
     */
    public function __construct(private readonly Source $source, ?string $text = null, ?int $first = null)
    {
        $this->first = $first ?? $source->templateLine;
        $this->text = preg_replace_callback(
            '/\{\{--.*?--\}\}/s',
            static fn (array $comment): string => preg_replace('/[^\n]/', ' ', $comment[0]),
            $text ?? $source->template,
        );

        for ($at = strpos($this->text, "\n"); $at !== false; $at = strpos($this->text, "\n", $at + 1)) {
            $this->starts[] = $at + 1;
        }
    }

    /**
     * @return list<Node>
     * @throws SharpCompileError
     */
    public function nodes(): array
    {
        if (($open = strpos($this->text, '{{--')) !== false) {
            $this->fail('{{-- has no --}}', $open);
        }

        return $this->until([])[0];
    }

    /**
     * Nodes up to one of $ends, which is taken and returned with them.
     *
     * @param list<string> $ends
     * @return array{list<Node>, string, int} the nodes, what ended them, and where that is
     */
    private function until(array $ends, ?string $inside = null, int $opened = 0): array
    {
        $nodes = [];
        $text = '';
        $text_at = $this->at;
        $length = strlen($this->text);

        $flush = function () use (&$nodes, &$text, &$text_at): void {
            if ($text !== '') {
                $line = $this->line($text_at + strspn($text, " \t\n"));
                $nodes[] = new Node(NodeType::TEXT, $line, $text);
                $text = '';
            }
        };

        while ($this->at < $length) {
            $at = $this->at;
            if ($text === '') {
                $text_at = $at;
            }

            // @{{ and @@ are the characters themselves.
            if ($this->next('@{{') || $this->next('@@')) {
                $literal = $this->next('@@') ? '@' : '{{';
                $text .= $literal;
                $this->at += strlen($literal) + 1;
                continue;
            }

            if ($this->next('{{')) {
                $flush();
                $close = Php::end($this->text, $at + 2, '}}') ?? $this->fail('{{ has no }}', $at);
                $nodes[] = new Node(NodeType::ECHO, $this->line($at), trim(substr($this->text, $at + 2, $close - $at - 2)));
                $this->at = $close + 2;
                continue;
            }

            if (preg_match('/\G<\/([A-Za-z][\w-]*)\s*>/', $this->text, $close, 0, $at)) {
                $flush();
                $this->at += strlen($close[0]);

                return in_array("</{$close[1]}>", $ends, true)
                    ? [$nodes, "</{$close[1]}>", $at]
                    : $this->fail("{$close[0]} closes nothing here".$this->unclosed($inside, $opened), $at);
            }

            if (preg_match('/\G<([A-Za-z][\w-]*)/', $this->text, $open, 0, $at)) {
                $flush();
                $this->at += strlen($open[0]);
                $nodes[] = $this->tag($open[1], $at);
                continue;
            }

            if ($this->next('<?')) {
                $this->fail('PHP in a template goes in @php ... @endphp, or {{ }} for a value', $at);
            }

            // Not in the middle of a word: name@example.com is text.
            if (($at === 0 || ! ctype_alnum($this->text[$at - 1])) && preg_match(self::DIRECTIVE, $this->text, $found, 0, $at)) {
                $flush();
                $this->at += strlen($found[0]);

                if (in_array($found[0], $ends, true)) {
                    return [$nodes, $found[0], $at];
                }

                $nodes[] = match ($found[1]) {
                    'if' => $this->condition($at),
                    'foreach' => $this->loop($at),
                    'php' => $this->php($at),
                    default => $this->fail("{$found[0]} is out of place".$this->unclosed($inside, $opened), $at),
                };
                continue;
            }

            $text .= $this->text[$at];
            $this->at++;
        }

        $flush();
        if ($ends !== []) {
            $this->fail("{$inside} is never closed", $opened);
        }

        return [$nodes, '', $this->at];
    }

    private function tag(string $name, int $opened): Node
    {
        $attributes = [];

        while (true) {
            $this->at += strspn($this->text, " \t\n", $this->at);
            $at = $this->at;

            if ($at >= strlen($this->text)) {
                $this->fail("<{$name} has no >", $opened);
            }
            if ($this->next('/>')) {
                $this->at += 2;

                return new Node(NodeType::TAG, $this->line($opened), $name, $attributes);
            }
            if ($this->next('>')) {
                $this->at++;
                [$children] = $this->until(["</{$name}>"], "<{$name}>", $opened);

                return new Node(NodeType::TAG, $this->line($opened), $name, $attributes, $children);
            }
            if (! preg_match('/\G([:@]?)([A-Za-z][\w-]*)/', $this->text, $found, 0, $at)) {
                $this->fail("Cannot read an attribute of <{$name}> at '".strtok(substr($this->text, $at, 20), "\n")."'", $at);
            }

            $this->at += strlen($found[0]);
            $value = null;

            if (preg_match('/\G\s*=\s*/', $this->text, $equals, 0, $this->at)) {
                $this->at += strlen($equals[0]);
                $quote = $this->text[$this->at] ?? '';
                if ($quote !== '"' && $quote !== "'") {
                    $this->fail("The value of {$found[0]} on <{$name}> needs quotes", $at);
                }
                $end = strpos($this->text, $quote, $this->at + 1);
                if ($end === false) {
                    $this->fail("The value of {$found[0]} on <{$name}> has no closing quote", $at);
                }
                $value = substr($this->text, $this->at + 1, $end - $this->at - 1);
                $this->at = $end + 1;
            } elseif ($found[1] !== '') {
                $this->fail("{$found[0]} on <{$name}> needs a value", $at);
            }

            $camel = lcfirst(str_replace('-', '', ucwords($found[2], '-')));
            foreach ($attributes as $already) {
                if ($already->name === $camel && ($already->prefix === '@') === ($found[1] === '@')) {
                    $this->fail("<{$name}> has {$camel} twice", $at);
                }
            }
            $attributes[] = new Attribute($found[1], $camel, $value, $this->line($at));
        }
    }

    private function condition(int $opened): Node
    {
        $branches = [];
        $directive = '@if';
        $at = $opened;

        while ($directive !== '@endif') {
            $expression = $directive === '@else' ? '' : $this->argument($directive, $at);
            $ends = $directive === '@else' ? ['@endif'] : ['@elseif', '@else', '@endif'];
            $line = $this->line($at);

            [$nodes, $directive, $at] = $this->until($ends, '@if', $opened);
            $branches[] = new Node(NodeType::BRANCH, $line, $expression, children: $nodes);
        }

        return new Node(NodeType::IF, $this->line($opened), children: $branches);
    }

    private function loop(int $opened): Node
    {
        $expression = $this->argument('@foreach', $opened);
        [$nodes] = $this->until(['@endforeach'], '@foreach', $opened);

        return new Node(NodeType::LOOP, $this->line($opened), $expression, children: $nodes);
    }

    private function php(int $opened): Node
    {
        $end = strpos($this->text, '@endphp', $this->at);
        if ($end === false) {
            $this->fail('@php has no @endphp', $opened);
        }
        $code = substr($this->text, $this->at, $end - $this->at);
        $line = $this->line($this->at + strspn($code, " \t\n"));
        $this->at = $end + strlen('@endphp');

        return new Node(NodeType::PHP, $line, trim($code));
    }

    private function argument(string $directive, int $at): string
    {
        $this->at += strspn($this->text, " \t", $this->at);
        if (! $this->next('(')) {
            $this->fail("{$directive} needs ( )", $at);
        }
        $close = Php::end($this->text, $this->at + 1, ')') ?? $this->fail("{$directive}( has no )", $at);
        $argument = trim(substr($this->text, $this->at + 1, $close - $this->at - 1));
        if ($argument === '') {
            $this->fail("{$directive}() is empty", $at);
        }
        $this->at = $close + 1;

        return $argument;
    }

    private function next(string $expected): bool
    {
        return substr_compare($this->text, $expected, $this->at, strlen($expected)) === 0;
    }

    private function unclosed(?string $inside, int $opened): string
    {
        return is_null($inside) ? '' : ": {$inside} from line {$this->line($opened)} is still open";
    }

    private function line(int $at): int
    {
        [$low, $high] = [0, count($this->starts) - 1];
        while ($low < $high) {
            $middle = intdiv($low + $high + 1, 2);
            $this->starts[$middle] <= $at ? $low = $middle : $high = $middle - 1;
        }

        return $this->first + $low;
    }

    /**
     * @throws SharpCompileError Always.
     */
    private function fail(string $problem, int $at): never
    {
        throw new SharpCompileError($problem, $this->source->file, $this->line($at));
    }
}
