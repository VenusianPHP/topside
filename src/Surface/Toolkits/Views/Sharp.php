<?php

namespace Surface\Toolkits\Views;

use Surface\Toolkits\Menus\MenuBar;
use Surface\Toolkits\Views\Sharp\Source;
use Surface\Toolkits\Views\Sharp\Script;
use Surface\Toolkits\Views\Sharp\TagKind;
use Surface\Toolkits\Views\Sharp\Compiler;
use Surface\Toolkits\Views\Sharp\Php;
use Surface\Toolkits\Views\Sharp\TagResolver;
use Surface\Toolkits\Views\Sharp\SharpCompileError;

/**
 * Sharp: markup views. welcome.sharp.php under resources/ compiles to a Component class, so
 * everything after that is what a hand-written component gets.
 *
 *     @script
 *         public int $count = 0;
 *         public function add(): void { $this->count++; }
 *     @endscript
 *
 *     @template
 *     <Column spacing="12" justify="center">
 *         <Label>Clicked {{ $count }} times</Label>
 *         <Button @click="add">Add</Button>
 *     </Column>
 *     @endtemplate
 *
 * A file may have a @menu ... @endmenu too, laid over the window's bar while it is on screen.
 *
 * Compiling never waits for a click: View::render() loads the view and every template its
 * markup can reach before it is mounted. Files are looked at again at each View::render(),
 * so an edit shows the next time the view is opened; re-renders after handlers read no file.
 */
final class Sharp
{
    private const string COMPILED = 'Surface\\Toolkits\\Views\\Sharp\\Compiled';

    private ?TagResolver $resolver = null;

    /** @var array<string, class-string<Component>> loaded since refresh(), by file */
    private array $classes = [];

    /** @var array<string, Script> loaded since refresh(), by file */
    private array $scripts = [];

    private ?string $compiler = null;

    /**
     * @param string $resources where the templates are
     * @param string $cache where the compiled classes go
     * @param string|null $namespace where an application's components are: App\Views
     */
    public function __construct(
        private readonly string $resources,
        private readonly string $cache,
        private readonly ?string $namespace = null,
    ) {}

    /**
     * The template's variables: the component's public properties.
     *
     * @return array<string, mixed>
     */
    public static function state(Component $component): array
    {
        return get_object_vars($component);
    }

    /**
     * Look at the files again: an edit, a new file.
     */
    public function refresh(): void
    {
        $this->resolver = null;
        $this->classes = [];
        $this->scripts = [];
    }

    /**
     * The class of a view by name: 'welcome' is resources/welcome.sharp.php, 'demo.index' is
     * resources/demo/index.sharp.php.
     *
     * @return class-string<Component>
     * @throws ViewException When there is no such file, or it does not compile.
     */
    public function view(string $name): string
    {
        if (! preg_match('/^[\w-]+(\.[\w-]+)*$/D', $name)) {
            throw new ViewException("No view [{$name}]: a view is a class extending ".Component::class.", or a name like 'demo.index'.");
        }

        $file = str_replace('.', '/', $name).'.sharp.php';
        if (! is_file("{$this->resources}/{$file}")) {
            throw new ViewException("No view [{$name}]: it is no class extending ".Component::class.", and there is no {$this->resources}/{$file}.");
        }

        return $this->load($file);
    }

    /**
     * The class of a file under resources/, compiled when its cache is missing or out of date.
     *
     * @return class-string<Component>
     * @throws SharpCompileError
     */
    public function load(string $file): string
    {
        if (isset($this->classes[$file])) {
            return $this->classes[$file];
        }

        $record = $this->cached($file) ?? $this->compile($file);
        require_once "{$this->cache}/{$record['script']}";
        require_once "{$this->cache}/{$record['path']}";
        $this->classes[$file] = $record['class'];

        try {
            // Recorded first, so templates that use themselves or each other end here.
            foreach ($record['templates'] as $template) {
                $this->load($template);
            }
        } catch (\Throwable $e) {
            unset($this->classes[$file]);
            throw $e;
        }

        return $record['class'];
    }

    /**
     * Compile every template under resources/, from nothing.
     *
     * @return list<string> the files
     */
    public function compileAll(): array
    {
        $this->refresh();
        foreach (glob("{$this->cache}/sharp-*") ?: [] as $stale) {
            unlink($stale);
        }

        $files = $this->resolver()->all();
        foreach ($files as $file) {
            $this->load($file);
        }

        return $files;
    }

    /**
     * The cache's record of $file, when nothing it was compiled from has changed: the file,
     * what its tags name, what those are written in, and the compiler.
     */
    private function cached(string $file): ?array
    {
        $path = $this->recordPath($file);
        $record = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (! is_array($record) || ($record['compiler'] ?? null) !== $this->compilerVersion()) {
            return null;
        }

        foreach ($record['sources'] as $source => $hash) {
            if (! is_file($source) || sha1_file($source) !== $hash) {
                return null;
            }
        }
        if (! is_file("{$this->cache}/{$record['path']}") || ! is_file("{$this->cache}/{$record['script']}")) {
            return null;
        }

        try {
            foreach ($record['tags'] as $name => $resolution) {
                if ($this->resolver()->resolve($name)->resolution() !== $resolution) {
                    return null;
                }
            }
        } catch (ViewException) {
            return null;    // compiling says it with the template's line
        }

        return $record;
    }

    private function compile(string $file): array
    {
        $script = $this->script($file);
        $short = $this->shortName($file, $script->source);

        [$body, $tags] = (new Compiler($script, $this->resolver()))->compile(self::COMPILED.'\\__NS__', $short);

        // A class cannot be declared twice, so each version of a template is a class of its own.
        $key = 'V'.substr(sha1($this->resources.$file.$body), 0, 16);
        $code = substr_replace($body, self::COMPILED."\\{$key}", strpos($body, self::COMPILED.'\\__NS__'), strlen(self::COMPILED.'\\__NS__'));
        $path = 'sharp-'.$this->slug($file)."-{$key}.php";
        $this->write($path, $code);

        $sources = ["{$this->resources}/{$file}" => sha1_file("{$this->resources}/{$file}")];
        $templates = [];
        $resolutions = [];
        foreach ($tags as $name => $tag) {
            $resolutions[$name] = $tag->resolution();
            if (is_file($tag->file)) {
                $sources[$tag->file] = sha1_file($tag->file);
            }
            if ($tag->kind === TagKind::TEMPLATE) {
                $templates[] = $tag->target;
            }
        }

        $record = [
            'compiler' => $this->compilerVersion(),
            'class' => self::COMPILED."\\{$key}\\{$short}",
            'path' => $path,
            'script' => $script->path,
            'sources' => $sources,
            'tags' => $resolutions,
            'templates' => $templates,
        ];
        $this->write(basename($this->recordPath($file)), json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return $record;
    }

    /**
     * A template's @script as a trait, loaded, so the compiler asks PHP what it declares.
     * Templates that use each other need only this of each other, so a cycle compiles.
     */
    private function script(string $file): Script
    {
        if (isset($this->scripts[$file])) {
            return $this->scripts[$file];
        }

        $content = (string) file_get_contents("{$this->resources}/{$file}");
        $source = Source::read("resources/{$file}", $content);
        $key = 'S'.substr(sha1($this->resources.$file.$source->imports.$source->scriptLine.$source->script), 0, 16);
        $namespace = self::COMPILED."\\{$key}";

        // On line 1 up to the script's first line, so each of its lines keeps its number.
        $code = "<?php namespace {$namespace}; {$source->imports} trait SharpScript {"
            .str_repeat("\n", max(1, $source->scriptLine - 1))."{$source->script}\n}\n";

        if ($error = Php::problem(substr($code, strlen('<?php ')))) {
            throw new SharpCompileError("@script does not parse: {$error->getMessage()}", $source->file, $error->getLine(), $error);
        }

        $path = 'sharp-'.$this->slug($file)."-{$key}.php";
        $this->write($path, $code);
        require_once "{$this->cache}/{$path}";

        $script = new Script($source, "{$namespace}\\SharpScript", $path);
        $declared = $script->reflect();
        if ($declared->hasMethod('render')) {
            $line = (int) $declared->getMethod('render')->getStartLine();
            throw new SharpCompileError('@script declares render(): the @template is the render()', $source->file, $line);
        }
        if ($declared->hasMethod('menu')) {
            $menu = $declared->getMethod('menu');
            $line = (int) $menu->getStartLine();
            if (! is_null($source->menu)) {
                throw new SharpCompileError('@script declares menu() and the file has @menu: a template has one or the other', $source->file, $line);
            }
            // Checked here: a class whose menu() does not fit Component's cannot be declared at all.
            $takes = $menu->getParameters();
            $fits = $menu->isPublic() && ! $menu->isStatic() && count($takes) === 1
                && (string) $takes[0]->getType() === MenuBar::class && (string) $menu->getReturnType() === MenuBar::class;
            if (! $fits) {
                throw new SharpCompileError('menu() is written public function menu(MenuBar $bar): MenuBar, with use '.MenuBar::class.'; at the top of @script', $source->file, $line);
            }
        }

        return $this->scripts[$file] = $script;
    }

    private function resolver(): TagResolver
    {
        return $this->resolver ??= new TagResolver($this->resources, $this->namespace, $this->script(...));
    }

    /**
     * The compiled class is named after its file, for readable paths (root.column1.card2) and errors.
     */
    private function shortName(string $file, Source $source): string
    {
        $name = str_replace(' ', '', ucwords(preg_replace('/[^A-Za-z0-9]+/', ' ', basename($file, '.sharp.php'))));
        $lower = strtolower($name);
        $reserved = ['bool', 'false', 'float', 'int', 'iterable', 'mixed', 'never', 'null', 'object', 'parent', 'self', 'string', 'true', 'void', 'sharpscript', 'enum'];

        $usable = preg_match('/^[A-Z]/', $name)
            && ! in_array($lower, [...$reserved, ...$source->aliases], true)
            && token_get_all("<?php {$name}")[1][0] === T_STRING;

        if ($usable) {
            return $name;
        }

        return preg_match('/^[A-Z]/', $name) ? "{$name}View" : "View{$name}";
    }

    private function slug(string $file): string
    {
        return str_replace('/', '.', substr($file, 0, -strlen('.sharp.php')));
    }

    private function recordPath(string $file): string
    {
        return "{$this->cache}/sharp-".sha1("{$this->resources}/{$file}").'.json';
    }

    private function compilerVersion(): string
    {
        return $this->compiler ??= sha1(__FILE__.sha1_file(__FILE__).implode('', array_map(sha1_file(...), glob(__DIR__.'/Sharp/*.php'))));
    }

    /**
     * All at once, so a process reading the cache never finds half a file.
     */
    private function write(string $name, string $content): void
    {
        if (! is_dir($this->cache) && ! @mkdir($this->cache, 0755, true) && ! is_dir($this->cache)) {
            throw new ViewException("Cannot make the folder for compiled views, {$this->cache}.");
        }
        if (! is_file("{$this->cache}/.gitignore")) {
            @file_put_contents("{$this->cache}/.gitignore", "*\n!.gitignore\n");
        }

        $temporary = "{$this->cache}/.".bin2hex(random_bytes(8)).'.tmp';
        if (@file_put_contents($temporary, $content) === false || ! @rename($temporary, "{$this->cache}/{$name}")) {
            throw new ViewException("Cannot write a compiled view to {$this->cache}.");
        }
    }
}
