<?php

namespace Surface\Toolkits\Views\Sharp;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Surface\Toolkits\Views\Sharp;
use Surface\Toolkits\Menus\Item;
use Surface\Toolkits\Menus\Menu;
use Surface\Toolkits\Menus\Toggle;
use Surface\Toolkits\Menus\Choice;
use Surface\Toolkits\Menus\MenuBar;
use Surface\Toolkits\Menus\Separator;
use Surface\Toolkits\Menus\AboutPanel;
use Surface\Toolkits\Menus\StandardItem;
use Surface\Toolkits\Menus\Runtime\MenuTag;
use Surface\Toolkits\Menus\Runtime\MenuBlock;

/**
 * Turns a @menu block into the component's menu(). Its tags are a vocabulary of their own,
 * read off the menu definitions' constructors: <Toggle> and <Separator> here are the menu's,
 * and in @template the elements'.
 *
 * @internal
 */
final class MenuCompiler
{
    private const array MENUS = ['Menu', 'EditMenu', 'WindowMenu'];

    private int $serial = 0;

    /** @var array<string, array<string, ReflectionParameter>> */
    private readonly array $vocabulary;

    public function __construct(
        private readonly Compiler $compiler,
        private readonly CodeWriter $out,
    ) {
        $menu = self::arguments(Menu::class, ['nodes']);
        $standard = self::arguments(StandardItem::class, ['panel']);

        $this->vocabulary = [
            'Menu' => $menu,
            'Item' => self::arguments(Item::class),
            'Toggle' => self::arguments(Toggle::class),
            'Choice' => self::arguments(Choice::class),
            'Separator' => self::arguments(Separator::class),
            'Standard' => $standard,
            'EditMenu' => array_intersect_key($menu, ['title' => true, 'enabled' => true]),
            'WindowMenu' => array_intersect_key($menu, ['title' => true, 'enabled' => true]),
            'AboutPanel' => self::arguments(AboutPanel::class) + array_diff_key($standard, ['kind' => true, 'id' => true, 'onClick' => true]),
            'AboutItem' => array_diff_key($standard, ['kind' => true]),
        ];
    }

    /**
     * @throws SharpCompileError
     */
    public function compile(Source $source): void
    {
        $this->out->at($source->menuLine, 'public function menu(\\'.MenuBar::class.' $__bar): \\'.MenuBar::class
            .' { extract(\\'.Sharp::class.'::state($this), EXTR_SKIP); $__m0 = [];');
        $this->content((new Lexer($source, $source->menu, $source->menuLine))->nodes(), '$__m0', null, false);
        $this->out->add('return \\'.MenuBlock::class.'::lay($__bar, $__m0, '.var_export($source->menuReplaces, true).'); } ');
    }

    /**
     * @param string $list the variable the tags are added to
     * @param string|null $inside the tag they are in
     * @param bool $extending inside extend, where an id may name what is there
     */
    private function content(array $nodes, string $list, ?string $inside, bool $extending): void
    {
        foreach ($nodes as $node) {
            switch ($node->type) {
                case NodeType::TEXT:
                    if (! $this->compiler->isSpace($node)) {
                        $this->compiler->fail("Text on its own in @menu: write it as a label, like <Item label=\"...\"/>", $node->line);
                    }
                    break;

                case NodeType::ECHO:
                    $this->compiler->fail('{{ }} on its own in @menu: put it in an attribute, like label="{{ $name }}"', $node->line);

                case NodeType::PHP:
                    $this->compiler->php($node);
                    break;

                case NodeType::TAG:
                    $this->tag($node, $list, $inside, $extending);
                    break;

                case NodeType::IF:
                    foreach ($node->children as $index => $branch) {
                        $this->out->at($branch->line, match (true) {
                            $branch->value === '' => '} else {',
                            $index === 0 => "if {$this->compiler->expression($branch->value, $branch->line)} {",
                            default => "} elseif {$this->compiler->expression($branch->value, $branch->line)} {",
                        });
                        $this->content($branch->children, $list, $inside, $extending);
                    }
                    $this->out->add('} ');
                    break;

                case NodeType::LOOP:
                    if ($error = Php::problem("foreach ({$node->value}) {}")) {
                        $this->compiler->fail("@foreach({$node->value}) is not a foreach: {$error->getMessage()}", $node->line);
                    }
                    $this->out->at($node->line, "foreach ({$node->value}) {");
                    $this->content($node->children, $list, $inside, $extending);
                    $this->out->add('} ');
                    break;
            }
        }
    }

    private function tag(Node $node, string $list, ?string $inside, bool $extending): void
    {
        $name = $node->value;
        $arguments = $this->vocabulary[$name]
            ?? $this->compiler->fail("<{$name}> is not a tag of @menu: it has ".implode(', ', array_keys($this->vocabulary)), $node->line);

        if (is_null($inside) && ! in_array($name, self::MENUS, true)) {
            $this->compiler->fail("<{$name}> goes inside a <Menu>: a bar holds menus", $node->line);
        }

        $extend = false;
        $written = [];
        foreach ($node->attributes as $attribute) {
            $argument = $attribute->name;

            if ($attribute->prefix === '@') {
                $argument = 'on'.ucfirst($attribute->name);
                if (! isset($arguments[$argument]) || ! self::isCallback($arguments[$argument])) {
                    $events = array_map(
                        static fn (string $event): string => '@'.lcfirst(substr($event, 2)),
                        array_keys(array_filter($arguments, self::isCallback(...))),
                    );
                    $has = $events === [] ? 'it has no events' : 'it has '.implode(', ', $events);
                    $this->compiler->fail("<{$name}> has no @{$attribute->name}: {$has}", $attribute->line);
                }
                $written[$argument] = $this->compiler->handler($attribute);
            } elseif ($name === 'Menu' && $argument === 'extend') {
                if ($attribute->prefix !== '' || ! in_array($attribute->value, [null, 'true', 'false'], true)) {
                    $this->compiler->fail('extend stands alone: <Menu key="file" extend>', $attribute->line);
                }
                $extend = $attribute->value !== 'false';
            } elseif ($name === 'Menu' && $argument === 'key') {
                $written[$argument] = $this->compiler->value($attribute, null);
            } elseif (! isset($arguments[$argument]) || self::isCallback($arguments[$argument])) {
                $known = array_keys(array_filter($arguments, static fn (ReflectionParameter $p): bool => ! self::isCallback($p)));
                if ($name === 'Menu') {
                    array_push($known, 'key', 'extend');
                }
                $has = $known === [] ? 'it has none' : 'it has '.implode(', ', $known);
                $this->compiler->fail("<{$name}> has no attribute {$argument}: {$has}", $attribute->line);
            } else {
                $written[$argument] = $this->compiler->value($attribute, $arguments[$argument]->getType());
            }
        }

        if (isset($written['key']) && ! $extend) {
            $this->compiler->fail('key names the menu to extend: add extend, or give a new menu id', $node->line);
        }

        // Inside extend an id may name what is there, and then nothing else is needed.
        $changes = $extending && (isset($written['id']) || $extend);
        if (! $changes && ! $extend) {
            $this->guardRequired($node, $arguments, $written);
        }

        $children = '[]';
        if (array_filter($node->children, fn (Node $child): bool => ! $this->compiler->isSpace($child)) !== []) {
            if ($name !== 'Menu') {
                $this->compiler->fail("<{$name}> holds nothing: close it with />", $node->line);
            }
            $children = '$__m'.(++$this->serial);
            $this->out->add("{$children} = []; ");
            $this->content($node->children, $children, $name, $extend || $changes);
        }

        $pairs = array_map(
            static fn (string $argument, string $code): string => var_export($argument, true)." => {$code}",
            array_keys($written),
            $written,
        );
        $this->out->at($node->line, "{$list}[] = new \\".MenuTag::class."({$node->line}, '{$name}', [".implode(', ', $pairs)."], {$children}, ".var_export($extend, true).');');
    }

    /**
     * @param array<string, ReflectionParameter> $arguments
     * @param array<string, string> $written
     */
    private function guardRequired(Node $node, array $arguments, array $written): void
    {
        if (in_array($node->value, ['EditMenu', 'WindowMenu'], true)) {
            return;    // they come with both
        }

        foreach ($arguments as $name => $argument) {
            if ($argument->isOptional() || isset($written[$name]) || ($name === 'title' && isset($written['application']))) {
                continue;
            }
            $this->compiler->fail("<{$node->value}> needs {$name}", $node->line);
        }
    }

    private static function isCallback(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();
        $types = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];

        return array_any($types, static fn ($one): bool => $one instanceof ReflectionNamedType && $one->getName() === Closure::class);
    }

    /**
     * @param class-string $class
     * @param list<string> $without
     * @return array<string, ReflectionParameter>
     */
    private static function arguments(string $class, array $without = []): array
    {
        $arguments = [];
        foreach ((new ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $parameter) {
            if (! in_array($parameter->getName(), $without, true)) {
                $arguments[$parameter->getName()] = $parameter;
            }
        }

        return $arguments;
    }
}
