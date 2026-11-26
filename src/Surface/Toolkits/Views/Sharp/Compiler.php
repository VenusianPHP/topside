<?php

namespace Surface\Toolkits\Views\Sharp;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use InvalidArgumentException;
use Surface\Toolkits\Views\Sharp;
use Surface\Toolkits\Views\Component;
use Surface\Toolkits\Views\ViewException;
use Surface\Toolkits\Views\Elements\Link;
use Surface\Toolkits\Views\Elements\Tab;
use Surface\Toolkits\Views\Elements\TabView;
use Surface\Toolkits\Views\Elements\Container;
use Surface\Toolkits\Views\Elements\Label;
use Surface\Toolkits\Views\Elements\Button;
use Surface\Toolkits\Views\Elements\Element;
use Surface\Toolkits\Views\Elements\Checkbox;
use Surface\Toolkits\Views\Elements\ToggleButton;
use Surface\Toolkits\Views\Elements\Children;
use Surface\Toolkits\Views\Elements\ComponentElement;
use Surface\Contracts\Toolkits\Styling\TextRun;

/**
 * Turns a template into a Component class: render() builds the same elements a hand-written
 * view would, so there is one Mounter and one kind of handler.
 *
 * The elements are the vocabulary: a tag's attributes are its make() arguments and fluent
 * setters, and its events its on*() methods. An element added to TagResolver needs nothing here.
 *
 * Identity follows the composition rules. Each child written in a template has a place of its
 * own: one left out by @if holds that place, and what a @foreach makes is matched by key.
 *
 * A @menu block becomes menu(), by the MenuCompiler, which reads values and handlers here.
 */
final class Compiler
{
    /** The argument a tag's text content fills. */
    private const array CONTENT = [
        Label::class => 'text',
        Button::class => 'label',
        Checkbox::class => 'label',
        ToggleButton::class => 'label',
        Link::class => 'text',
    ];

    private CodeWriter $out;

    private int $serial = 0;

    /** Keeps the keys of one loop apart from those of another, across templates too. */
    private readonly string $scope;

    private readonly ReflectionClass $self;

    /** @var list<string> the template's public properties */
    private readonly array $state;

    /** @var array<string, Tag> by tag name */
    private array $tags = [];

    public function __construct(
        private readonly Script $script,
        private readonly TagResolver $resolver,
    ) {
        $this->scope = substr(sha1($script->source->file), 0, 8);
        $this->self = $script->reflect();
        $this->state = self::props($this->self);
    }

    /**
     * @param string $namespace where the class goes
     * @param string $class its short name
     * @return array{string, array<string, Tag>} the PHP, and the tags it used
     * @throws SharpCompileError
     */
    public function compile(string $namespace, string $class): array
    {
        $source = $this->script->source;
        $this->out = new CodeWriter();
        $this->out->add("<?php namespace {$namespace}; {$source->imports} "
            ."final class {$class} extends \\".Component::class." { use \\{$this->script->trait}; ");

        // In the file's order, so each line of either block keeps its number.
        $menu = is_null($source->menu) ? null : new MenuCompiler($this, $this->out);
        $menuFirst = $source->menuLine < $source->templateLine;
        if ($menuFirst) {
            $menu?->compile($source);
        }
        $this->out->add('public function render(): \\'.Element::class.' { extract(\\'.Sharp::class.'::state($this), EXTR_SKIP); ');

        $root = null;
        foreach ((new Lexer($source))->nodes() as $node) {
            if ($this->isSpace($node)) {
                continue;
            }
            if ($node->type === NodeType::PHP) {
                $this->php($node);
                continue;
            }
            if ($node->type !== NodeType::TAG) {
                $this->fail('A template has one root tag: put this inside a <Column> or <Row>', $node->line);
            }
            if (! is_null($root)) {
                $this->fail('A template has one root tag: put both inside a <Column> or <Row>', $node->line);
            }
            if ($node->value === 'Children') {
                $this->fail('<Children/> cannot be the root: put it inside a <Column> or <Row>', $node->line);
            }
            $root = $this->tag($node);
        }

        if (is_null($root)) {
            $this->fail('The template is empty', $source->templateLine);
        }

        $this->out->add("return {$root}; } ");
        if (! $menuFirst) {
            $menu?->compile($source);
        }
        $this->out->add("}\n");
        $code = $this->out->code();

        // Each piece was checked on its own; this is what they make together.
        if ($error = Php::problem(substr($code, strlen('<?php ')))) {
            throw new SharpCompileError($error->getMessage(), $source->file, $error->getLine(), $error);
        }

        return [$code, $this->tags];
    }

    /**
     * Write the statements that build a tag's element.
     *
     * @param string|null $loop the code its key is prefixed with, inside a @foreach
     * @return string the variable that holds the element
     */
    private function tag(Node $node, ?string $loop = null): string
    {
        $tag = $this->resolve($node);
        $var = '$__e'.(++$this->serial);

        [$make, $calls] = $tag->kind === TagKind::ELEMENT
            ? $this->element($tag, $node)
            : $this->component($tag, $node);

        $key = $this->attribute($node, 'key');
        if (! is_null($key)) {
            $value = $this->value($key, null);
            $calls[] = '->key('.(is_null($loop) ? $value : "{$loop}.({$value})").')';
        } elseif (! is_null($loop)) {
            $this->fail("<{$node->value}> in a @foreach needs a key, like :key=\"\$item->id\", so a reorder moves it instead of rebuilding it", $node->line);
        }

        $this->out->at($node->line, "{$var} = {$make}".implode('', $calls).';');

        $content = self::CONTENT[$tag->target] ?? null;
        if (is_null($content) && array_filter($node->children, fn (Node $child): bool => ! $this->isSpace($child)) !== []) {
            if ($tag->kind === TagKind::ELEMENT && ! $tag->declares->isSubclassOf(Container::class)) {
                $this->fail("<{$node->value}> holds nothing: close it with />", $node->line);
            }
            if ($tag->target === TabView::class) {
                $this->guardTabs($node->children);
            }
            $this->children($node, $var);
        }

        return $var;
    }

    /**
     * A TabView holds Tabs, or a component whose root is one.
     *
     * @param list<Node> $nodes
     */
    private function guardTabs(array $nodes): void
    {
        foreach ($nodes as $node) {
            if ($node->type === NodeType::TAG) {
                $tag = $this->resolve($node);
                if ($tag->kind === TagKind::ELEMENT && ! in_array($tag->target, [Tab::class, Children::class], true)) {
                    $this->fail("<TabView> holds <Tab>s: put <{$node->value}> inside a <Tab title=\"...\">", $node->line);
                }
            } elseif (in_array($node->type, [NodeType::IF, NodeType::BRANCH, NodeType::LOOP], true)) {
                $this->guardTabs($node->children);
            }
        }
    }

    /**
     * @return array{string, list<string>} the make() and the calls chained to it
     */
    private function element(Tag $tag, Node $node): array
    {
        if ($tag->target === Children::class) {
            if ($node->attributes !== []) {
                $this->fail('<Children/> takes no attributes', $node->line);
            }

            return ['\\'.Children::class.'::slot()', []];
        }

        $make = $tag->declares->getMethod('make');
        $params = [];
        foreach ($make->getParameters() as $param) {
            $params[$param->getName()] = $param;
        }

        $arguments = [];
        $calls = [];
        foreach ($node->attributes as $attribute) {
            $name = $attribute->name;

            if ($attribute->prefix === '@') {
                $method = 'on'.ucfirst($name);
                $events = self::events($tag->declares);
                if (! in_array($method, $events, true)) {
                    $has = $events === [] ? 'it has no events' : 'it has @'.implode(', @', array_map(static fn (string $on): string => lcfirst(substr($on, 2)), $events));
                    $this->fail("<{$node->value}> has no @{$name}: {$has}", $attribute->line);
                }
                $calls[] = "->{$method}({$this->handler($attribute)})";
            } elseif ($name === 'key') {
                continue;
            } elseif (isset($params[$name])) {
                $arguments[$name] = $this->value($attribute, $params[$name]->getType());
            } else {
                $calls[] = $this->setting($node, $attribute, $tag->declares, array_keys($params));
            }
        }

        if ($tag->target === Label::class && $this->hasRuns($node)) {
            if (isset($arguments['text'])) {
                $this->fail("<{$node->value}> has both a text attribute and text inside: use one", $node->line);
            }
            $runs = '$__r'.(++$this->serial);
            $this->out->at($node->line, "{$runs} = {$this->runs($node)};");
            $arguments['text'] = '\\'.TextRun::class."::join({$runs})";
            $calls[] = "->runs({$runs})";
        } elseif (! is_null($content = self::CONTENT[$tag->target] ?? null)) {
            $text = $this->text($node);
            if (! is_null($text) && isset($arguments[$content])) {
                $this->fail("<{$node->value}> has both a {$content} attribute and text inside: use one", $node->line);
            }
            if (! is_null($text)) {
                $arguments[$content] = $text;
            }
        }

        foreach ($params as $name => $param) {
            if (! $param->isOptional() && ! isset($arguments[$name])) {
                $inside = $name === $content ? ", or text inside: <{$node->value}>...</{$node->value}>" : '';
                $this->fail("<{$node->value}> needs {$name}{$inside}", $node->line);
            }
        }

        $named = array_map(static fn (string $name, string $code): string => "{$name}: {$code}", array_keys($arguments), $arguments);

        return ["\\{$tag->target}::make(".implode(', ', $named).')', $calls];
    }

    /**
     * A component's public properties are its attributes first; what is left are the settings
     * laid over its root. @closed="..." gives its onClosed property a handler.
     *
     * @return array{string, list<string>}
     */
    private function component(Tag $tag, Node $node): array
    {
        $props = self::props($tag->declares);
        $assigned = [];
        $calls = [];

        foreach ($node->attributes as $attribute) {
            $name = $attribute->name;

            if ($attribute->prefix === '@') {
                $prop = 'on'.ucfirst($name);
                if (! in_array($prop, $props, true)) {
                    $this->fail("<{$node->value}> has no @{$name}: give it a public ?Closure \${$prop} property", $attribute->line);
                }
                $code = $this->handler($attribute);
            } elseif ($name === 'key') {
                continue;
            } elseif (in_array($name, $props, true)) {
                $prop = $name;
                $code = $this->value($attribute, $tag->declares->getProperty($name)->getType());
            } else {
                $calls[] = $this->setting($node, $attribute, new ReflectionClass(ComponentElement::class), $props);
                continue;
            }

            $assigned[] = var_export($prop, true)." => {$code}";
        }

        // A template's class changes with its content, so it is looked up by its file.
        $class = $tag->kind === TagKind::COMPONENT
            ? "\\{$tag->target}::class"
            : 'app(\\'.Sharp::class.'::class)->load('.var_export($tag->target, true).')';

        return ['(new \\'.ComponentElement::class."({$class}, [".implode(', ', $assigned).']))', $calls];
    }

    /**
     * An attribute that is a fluent setter of the element.
     *
     * @param list<string> $own what else the tag takes, for the message
     */
    private function setting(Node $node, Attribute $attribute, ReflectionClass $class, array $own): string
    {
        $setters = self::setters($class);
        $name = $attribute->name;
        if (! isset($setters[$name])) {
            $known = implode(', ', array_unique([...$own, ...array_keys($setters), 'key']));
            $this->fail("<{$node->value}> has no attribute {$name}: it has {$known}", $attribute->line);
        }

        $params = $setters[$name]->getParameters();
        $text = $attribute->value;

        if ($attribute->prefix === ':') {
            $code = $this->expression($text, $attribute->line);

            return match (true) {
                count($params) === 1 => "->{$name}({$code})",
                // One bool for both axes, or the two of them in an array.
                $name === 'fill' => "->fill(...(is_array(\$__v = {$code}) ? \$__v : [\$__v, \$__v]))",
                default => "->{$name}(...(is_array(\$__v = {$code}) ? \$__v : [\$__v]))",
            };
        }

        if (count($params) === 1) {
            return "->{$name}({$this->value($attribute, $params[0]->getType())})";
        }

        if ($name === 'fill') {
            $text = match ($text) {
                'true' => 'true true',
                'false' => 'false false',
                'horizontal' => 'true false',
                'vertical' => 'false true',
                default => $text,
            };
        }

        // More than one argument is separated by spaces: minSize="340 0".
        $words = is_null($text) ? [] : preg_split('/\s+/', trim($text), flags: PREG_SPLIT_NO_EMPTY);
        $least = count(array_filter($params, static fn ($param): bool => ! $param->isOptional()));
        if (count($words) < $least || count($words) > count($params)) {
            $takes = implode(' ', array_map(static fn ($param): string => $param->getName(), $params));
            $this->fail("{$name} on <{$node->value}> takes \"{$takes}\"", $attribute->line);
        }

        $values = [];
        foreach ($words as $index => $word) {
            $values[] = $this->literal($attribute, $word, $params[$index]->getType());
        }

        return "->{$name}(".implode(', ', $values).')';
    }

    public function value(Attribute $attribute, ?\ReflectionType $type): string
    {
        if ($attribute->prefix === ':') {
            return $this->expression($attribute->value, $attribute->line);
        }
        if (! is_null($attribute->value) && str_contains($attribute->value, '{{')) {
            if (! Values::takesString($type)) {
                $this->fail("{$attribute->name} is not text: write :{$attribute->name}=\"...\" for a PHP value", $attribute->line);
            }

            return $this->interpolated($attribute);
        }

        return $this->literal($attribute, is_null($attribute->value) ? null : self::decoded($attribute->value), $type);
    }

    private function literal(Attribute $attribute, ?string $text, ?\ReflectionType $type): string
    {
        try {
            return Values::code($type, $text);
        } catch (InvalidArgumentException $e) {
            throw new SharpCompileError("{$attribute->name}: {$e->getMessage()}", $this->script->source->file, $attribute->line, $e);
        }
    }

    private function interpolated(Attribute $attribute): string
    {
        $text = $attribute->value;
        $parts = [];
        $at = 0;

        while (($open = strpos($text, '{{', $at)) !== false) {
            $close = Php::end($text, $open + 2, '}}') ?? $this->fail('{{ has no }}', $attribute->line);
            if ($open > $at) {
                $parts[] = var_export(self::decoded(substr($text, $at, $open - $at)), true);
            }
            $parts[] = '(string) '.$this->expression(substr($text, $open + 2, $close - $open - 2), $attribute->line);
            $at = $close + 2;
        }
        if ($at < strlen($text)) {
            $parts[] = var_export(self::decoded(substr($text, $at)), true);
        }

        return implode(' . ', $parts);
    }

    /** The tags a label's text may hold, and the one attribute a <span> takes. */
    private const array RUN_TAGS = ['b', 'i', 'span'];

    /**
     * True when a label holds a tag: its text is then runs, of <b>, <i> and <span>.
     */
    private function hasRuns(Node $node): bool
    {
        foreach ($node->children as $child) {
            if ($child->type === NodeType::TAG) {
                return true;
            }
        }

        return false;
    }

    /**
     * A label's content as a list of TextRuns: text and {{ }} take the look of the tags round
     * them, <b> bold, <i> italic, <span color="#rrggbb"> (or :color) a colour. Runs of white
     * space are one space, and the ends are trimmed, as for plain text.
     */
    private function runs(Node $node): string
    {
        /** @var list<array{string, bool, bool, ?string, bool}> code, bold, italic, colour, literal */
        $pieces = [];
        $this->collectRuns($node, $node->children, false, false, null, $pieces);

        while ($pieces !== [] && $pieces[0][4] && trim($pieces[0][0]) === '') {
            array_shift($pieces);
        }
        while ($pieces !== [] && $pieces[count($pieces) - 1][4] && trim($pieces[count($pieces) - 1][0]) === '') {
            array_pop($pieces);
        }
        if ($pieces !== [] && $pieces[0][4]) {
            $pieces[0][0] = ltrim($pieces[0][0], ' ');
        }
        $last = count($pieces) - 1;
        if ($last >= 0 && $pieces[$last][4]) {
            $pieces[$last][0] = rtrim($pieces[$last][0], ' ');
        }

        /** @var list<array{list<string>, bool, bool, ?string}> $runs the parts of each run and its look */
        $runs = [];
        foreach ($pieces as [$value, $bold, $italic, $color, $literal]) {
            $code = $literal ? var_export(self::decoded($value), true) : $value;
            $end = count($runs) - 1;
            if ($end >= 0 && $runs[$end][1] === $bold && $runs[$end][2] === $italic && $runs[$end][3] === $color) {
                $runs[$end][0][] = $code;
            } else {
                $runs[] = [[$code], $bold, $italic, $color];
            }
        }

        $made = array_map(static function (array $run): string {
            [$parts, $bold, $italic, $color] = $run;
            $look = ($bold ? ', bold: true' : '').($italic ? ', italic: true' : '').(is_null($color) ? '' : ", color: {$color}");

            return 'new \\'.TextRun::class.'('.implode(' . ', $parts).$look.')';
        }, $runs);

        return '['.implode(', ', $made).']';
    }

    /**
     * @param list<Node> $nodes
     * @param list<array{string, bool, bool, ?string, bool}> $pieces
     */
    private function collectRuns(Node $label, array $nodes, bool $bold, bool $italic, ?string $color, array &$pieces): void
    {
        foreach ($nodes as $node) {
            if ($node->type === NodeType::TEXT) {
                $pieces[] = [preg_replace('/[ \t\n]+/', ' ', $node->value), $bold, $italic, $color, true];
                continue;
            }
            if ($node->type === NodeType::ECHO) {
                $pieces[] = ['(string) '.$this->expression($node->value, $node->line), $bold, $italic, $color, false];
                continue;
            }
            if ($node->type !== NodeType::TAG || ! in_array($node->value, self::RUN_TAGS, true)) {
                $this->fail("<{$label->value}> holds text, {{ }}, <b>, <i> and <span color=\"...\">: for a choice of text use {{ \$x ? 'a' : 'b' }}", $node->line);
            }

            $inner = $color;
            foreach ($node->attributes as $attribute) {
                if ($node->value !== 'span' || $attribute->name !== 'color' || $attribute->prefix === '@') {
                    $this->fail("<{$node->value}> in a label takes ".($node->value === 'span' ? 'only color' : 'no attributes'), $attribute->line);
                }
                $inner = $this->value($attribute, new \ReflectionParameter([TextRun::class, '__construct'], 'color')->getType());
            }
            if ($node->value === 'span' && $node->attributes === []) {
                $this->fail('<span> in a label needs a color', $node->line);
            }

            $this->collectRuns($label, $node->children, $bold || $node->value === 'b', $italic || $node->value === 'i', $inner, $pieces);
        }
    }

    /**
     * A text tag's content as one string, runs of white space as one space.
     */
    private function text(Node $node): ?string
    {
        $pieces = $node->children;
        foreach ($pieces as $piece) {
            if ($piece->type !== NodeType::TEXT && $piece->type !== NodeType::ECHO) {
                $this->fail("<{$node->value}> holds only text and {{ }}: for a choice of text use {{ \$x ? 'a' : 'b' }}", $piece->line);
            }
        }
        while ($pieces !== [] && $this->isSpace($pieces[0])) {
            array_shift($pieces);
        }
        while ($pieces !== [] && $this->isSpace($pieces[count($pieces) - 1])) {
            array_pop($pieces);
        }

        $parts = [];
        foreach ($pieces as $index => $piece) {
            if ($piece->type === NodeType::ECHO) {
                $parts[] = '(string) '.$this->expression($piece->value, $piece->line);
                continue;
            }
            $text = preg_replace('/[ \t\n]+/', ' ', $piece->value);
            $text = $index === 0 ? ltrim($text, ' ') : $text;
            $text = $index === count($pieces) - 1 ? rtrim($text, ' ') : $text;
            $parts[] = var_export(self::decoded($text), true);
        }

        return $parts === [] ? null : implode(' . ', $parts);
    }

    /**
     * Each child site gets a list: what it holds when left out (a null for its place, nothing
     * when it is matched by key), then what it rendered. A @foreach is one site, so what its
     * turns make stays in the order they made it.
     */
    private function children(Node $parent, string $var): void
    {
        $list = '$__c'.(++$this->serial);
        $left_out = [];
        $this->reserve($parent->children, $left_out);

        $this->out->add("{$list} = [".implode(', ', $left_out).']; ');
        $site = 0;
        $this->content($parent->children, $list, $site);
        $this->out->add("{$var}->with(...array_merge(...{$list})); ");
    }

    /**
     * @param list<string> $left_out
     */
    private function reserve(array $nodes, array &$left_out): void
    {
        foreach ($nodes as $node) {
            if ($node->type === NodeType::TAG) {
                $left_out[] = is_null($this->attribute($node, 'key')) ? '[null]' : '[]';
            } elseif ($node->type === NodeType::LOOP) {
                $left_out[] = '[]';
            } elseif (in_array($node->type, [NodeType::IF, NodeType::BRANCH], true)) {
                $this->reserve($node->children, $left_out);
            }
        }
    }

    private function content(array $nodes, string $list, int &$site, ?string $loop = null, int $loop_site = 0): void
    {
        foreach ($nodes as $node) {
            switch ($node->type) {
                case NodeType::TEXT:
                    if (! $this->isSpace($node)) {
                        $this->fail("Text on its own: put '".trim($node->value)."' inside a <Label>", $node->line);
                    }
                    break;

                case NodeType::ECHO:
                    $this->fail("{{ }} on its own: put it inside a <Label>", $node->line);

                case NodeType::PHP:
                    $this->php($node);
                    break;

                case NodeType::TAG:
                    $at = is_null($loop) ? $site++ : $loop_site;
                    if ($node->value === 'Children' && ! is_null($loop)) {
                        $this->fail('<Children/> cannot repeat in a @foreach', $node->line);
                    }
                    $prefix = is_null($loop) ? null : $loop.'."'.(++$this->serial).'\\x00"';
                    $element = $this->tag($node, $prefix);
                    $this->out->add(is_null($loop) ? "{$list}[{$at}] = [{$element}]; " : "{$list}[{$at}][] = {$element}; ");
                    break;

                case NodeType::IF:
                    foreach ($node->children as $index => $branch) {
                        $this->out->at($branch->line, match (true) {
                            $branch->value === '' => '} else {',
                            $index === 0 => "if {$this->expression($branch->value, $branch->line)} {",
                            default => "} elseif {$this->expression($branch->value, $branch->line)} {",
                        });
                        $this->content($branch->children, $list, $site, $loop, $loop_site);
                    }
                    $this->out->add('} ');
                    break;

                case NodeType::LOOP:
                    if ($error = Php::problem("foreach ({$node->value}) {}")) {
                        $this->fail("@foreach({$node->value}) is not a foreach: {$error->getMessage()}", $node->line);
                    }
                    // A loop inside a loop also counts which turn of the outer one it is in.
                    $at = is_null($loop) ? $site++ : $loop_site;
                    // \x00 and not \0, which would read digits after it as octal.
                    $scope = $loop ?? "\"\\x00{$this->scope}:\"";
                    $turn = '$__i'.(++$this->serial);
                    $inner = $scope;
                    if ($this->hasLoop($node->children)) {
                        $inner = "{$scope}.{$turn}.'/'";
                        $this->out->add("{$turn} = -1; ");
                    }
                    $this->out->at($node->line, "foreach ({$node->value}) {");
                    if ($inner !== $scope) {
                        $this->out->add("{$turn}++; ");
                    }
                    $this->content($node->children, $list, $site, $inner, $at);
                    $this->out->add('} ');
                    break;
            }
        }
    }

    private function hasLoop(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if ($node->type === NodeType::LOOP) {
                return true;
            }
            if (in_array($node->type, [NodeType::IF, NodeType::BRANCH], true) && $this->hasLoop($node->children)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @click="save" is $this->save(...), as composition writes it. @click="remove($id)" calls
     * it with those arguments, and @change="fn ($v) => $this->name = $v" is the closure itself.
     */
    public function handler(Attribute $attribute): string
    {
        $value = trim($attribute->value);

        if (preg_match('/^(static\s+)?(fn|function)\b\s*\(/', $value)) {
            $this->guardState($value, $attribute);

            return $this->expression($value, $attribute->line);
        }

        if (! preg_match('/^([A-Za-z_]\w*)\s*(?:\((.*)\))?$/sD', $value, $found)) {
            $this->fail("@{$attribute->name}=\"{$value}\" is not a method, a method with arguments, or a fn", $attribute->line);
        }

        [$method, $arguments] = [$found[1], $found[2] ?? null];
        if (! $this->self->hasMethod($method)) {
            $this->fail("@{$attribute->name}=\"{$value}\": @script has no method {$method}()", $attribute->line);
        }
        if (is_null($arguments) || trim($arguments) === '...') {
            return "\$this->{$method}(...)";
        }

        return $this->expression("fn () => \$this->{$method}({$arguments})", $attribute->line);
    }

    /**
     * A template's variables are copies, so $count++ in a handler would change nothing.
     */
    private function guardState(string $code, Attribute $attribute): void
    {
        $tokens = Php::tokens($code);
        $id = static fn (array|string|null $token): int|string|null => is_array($token) ? $token[0] : $token;
        $assigns = ['=', T_INC, T_DEC, T_PLUS_EQUAL, T_MINUS_EQUAL, T_MUL_EQUAL, T_DIV_EQUAL, T_CONCAT_EQUAL, T_COALESCE_EQUAL];

        foreach ($tokens as $index => $token) {
            if ($id($token) !== T_VARIABLE || ! in_array($name = substr($token[1], 1), $this->state, true)) {
                continue;
            }
            if (in_array($id($tokens[$index + 1] ?? null), $assigns, true) || in_array($id($tokens[$index - 1] ?? null), [T_INC, T_DEC], true)) {
                $this->fail("@{$attribute->name} changes a copy: write \$this->{$name} to change the state", $attribute->line);
            }
        }
    }

    public function expression(string $code, int $line): string
    {
        $code = trim($code);
        if ($code === '') {
            $this->fail('An empty expression', $line);
        }
        if ($error = Php::problem("return ({$code}\n);")) {
            $this->fail("'{$code}' is not a PHP expression: {$error->getMessage()}", $line);
        }

        // A trailing // comment would swallow the bracket.
        $comment = str_contains($code, '//') || str_contains($code, '#');

        return $comment ? "({$code}\n)" : "({$code})";
    }

    public function php(Node $node): void
    {
        if ($node->value === '') {
            return;
        }
        if ($error = Php::problem("{$node->value}\n;")) {
            $this->fail("@php does not parse: {$error->getMessage()}", $node->line + $error->getLine() - 1);
        }
        $this->out->at($node->line, $node->value."\n;");
    }

    private function resolve(Node $node): Tag
    {
        try {
            return $this->tags[$node->value] ??= $this->resolver->resolve($node->value);
        } catch (SharpCompileError $e) {
            throw $e;
        } catch (ViewException $e) {
            throw new SharpCompileError($e->getMessage(), $this->script->source->file, $node->line, $e);
        }
    }

    private function attribute(Node $node, string $name): ?Attribute
    {
        foreach ($node->attributes as $attribute) {
            if ($attribute->name === $name && $attribute->prefix !== '@') {
                return $attribute;
            }
        }

        return null;
    }

    public function isSpace(Node $node): bool
    {
        return $node->type === NodeType::TEXT && trim($node->value) === '';
    }

    private static function decoded(string $text): string
    {
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @return list<string>
     */
    private static function props(ReflectionClass $class): array
    {
        $public = array_filter($class->getProperties(ReflectionProperty::IS_PUBLIC), static fn (ReflectionProperty $p): bool => ! $p->isStatic());

        return array_values(array_map(static fn (ReflectionProperty $p): string => $p->getName(), $public));
    }

    /**
     * @return list<string>
     */
    private static function events(ReflectionClass $class): array
    {
        $names = array_map(static fn (ReflectionMethod $m): string => $m->getName(), $class->getMethods(ReflectionMethod::IS_PUBLIC));

        return array_values(array_filter($names, static fn (string $name): bool => preg_match('/^on[A-Z]/', $name) === 1));
    }

    /**
     * @return array<string, ReflectionMethod>
     */
    private static function setters(ReflectionClass $class): array
    {
        $setters = [];
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();
            if (! $method->isStatic() && (string) $method->getReturnType() === 'static' && $method->getParameters() !== []
                && ! in_array($name, ['with', 'key', 'inherit'], true) && ! preg_match('/^on[A-Z]/', $name)) {
                $setters[$name] = $method;
            }
        }

        return $setters;
    }

    /**
     * @throws SharpCompileError Always.
     */
    public function fail(string $problem, int $line): never
    {
        throw new SharpCompileError($problem, $this->script->source->file, $line);
    }
}
