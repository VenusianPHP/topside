<?php

namespace Surface\Toolkits\Views\Sharp;

use Closure;
use FilesystemIterator;
use ReflectionClass;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Surface\Toolkits\Views\Component;
use Surface\Toolkits\Views\ViewException;
use Surface\Toolkits\Views\Elements\Row;
use Surface\Toolkits\Views\Elements\Label;
use Surface\Toolkits\Views\Elements\Column;
use Surface\Toolkits\Views\Elements\Button;
use Surface\Toolkits\Views\Elements\Spacer;
use Surface\Toolkits\Views\Elements\Toggle;
use Surface\Toolkits\Views\Elements\Checkbox;
use Surface\Toolkits\Views\Elements\Children;
use Surface\Toolkits\Views\Elements\Dropdown;
use Surface\Toolkits\Views\Elements\Link;
use Surface\Toolkits\Views\Elements\ComboBox;
use Surface\Toolkits\Views\Elements\TextInput;
use Surface\Toolkits\Views\Elements\Datepicker;
use Surface\Toolkits\Views\Elements\Separator;
use Surface\Toolkits\Views\Elements\ProgressBar;
use Surface\Toolkits\Views\Elements\Spinner;
use Surface\Toolkits\Views\Elements\Slider;
use Surface\Toolkits\Views\Elements\ToggleButton;
use Surface\Toolkits\Views\Elements\SegmentedControl;
use Surface\Toolkits\Views\Elements\Image;
use Surface\Toolkits\Views\Elements\TextArea;
use Surface\Toolkits\Views\Elements\ListView;
use Surface\Toolkits\Views\Elements\CollectionView;
use Surface\Toolkits\Views\Elements\Table;
use Surface\Toolkits\Views\Elements\Tree;
use Surface\Toolkits\Views\Elements\ScrollView;
use Surface\Toolkits\Views\Elements\TabView;
use Surface\Toolkits\Views\Elements\Tab;
use Surface\Toolkits\Views\Elements\SplitView;
use Surface\Toolkits\Views\Elements\Grid;
use Surface\Toolkits\Views\Elements\Overlay;

/**
 * What a tag means:
 *
 *  1. A built-in name is that element, and so that TK primitive. These names are reserved.
 *  2. A Component class of that name in App\Views.
 *  3. A kebab-case sharp file anywhere under resources/: <ImageCarousel> is image-carousel.sharp.php.
 *
 * Both 2 and 3, or two files of one name, is an error: neither silently wins.
 */
final class TagResolver
{
    /** @var array<string, class-string> */
    public const array ELEMENTS = [
        'Column' => Column::class,
        'Row' => Row::class,
        'Overlay' => Overlay::class,
        'Spacer' => Spacer::class,
        'Children' => Children::class,
        'Label' => Label::class,
        'Button' => Button::class,
        'Checkbox' => Checkbox::class,
        'Toggle' => Toggle::class,
        'Dropdown' => Dropdown::class,
        'Link' => Link::class,
        'TextInput' => TextInput::class,
        'ComboBox' => ComboBox::class,
        'Datepicker' => Datepicker::class,
        'Separator' => Separator::class,
        'ProgressBar' => ProgressBar::class,
        'Spinner' => Spinner::class,
        'Slider' => Slider::class,
        'ToggleButton' => ToggleButton::class,
        'SegmentedControl' => SegmentedControl::class,
        'Image' => Image::class,
        'ScrollView' => ScrollView::class,
        'TabView' => TabView::class,
        'Tab' => Tab::class,
        'SplitView' => SplitView::class,
        'Grid' => Grid::class,
        'TextArea' => TextArea::class,
        'ListView' => ListView::class,
        'CollectionView' => CollectionView::class,
        'Table' => Table::class,
        'Tree' => Tree::class,
    ];

    /** @var array<string, list<string>>|null sharp files under resources/, by file name */
    private ?array $templates = null;

    /**
     * @param string|null $namespace where an application's components are: App\Views
     * @param Closure(string): Script $script loads the @script of a file under resources/
     */
    public function __construct(
        private readonly string $resources,
        private readonly ?string $namespace,
        private readonly Closure $script,
    ) {}

    /**
     * @throws ViewException When the name means nothing, or two things.
     */
    public function resolve(string $name): Tag
    {
        if (isset(self::ELEMENTS[$name])) {
            $class = new ReflectionClass(self::ELEMENTS[$name]);

            return new Tag($name, TagKind::ELEMENT, $class->getName(), $class, $class->getFileName());
        }

        if (! preg_match('/^[A-Z][A-Za-z0-9]*$/D', $name)) {
            $meant = str_replace('-', '', ucwords($name, '-'));
            throw new ViewException("<{$name}> is not a tag: tags are PascalCase, like <{$meant}>");
        }

        $kebab = self::kebab($name);
        $files = $this->templates()[$kebab] ?? [];
        $class = $this->component($name);

        if (count($files) + (is_null($class) ? 0 : 1) > 1) {
            $claims = implode(', ', [...(is_null($class) ? [] : [$class->getName()]), ...array_map(static fn (string $file): string => "resources/{$file}", $files)]);
            throw new ViewException("<{$name}> could be any of {$claims}: rename all but one");
        }
        if (! is_null($class)) {
            return new Tag($name, TagKind::COMPONENT, $class->getName(), $class, (string) $class->getFileName());
        }
        if ($files !== []) {
            return new Tag($name, TagKind::TEMPLATE, $files[0], ($this->script)($files[0])->reflect(), "{$this->resources}/{$files[0]}");
        }

        $looked = [...(is_null($this->namespace) ? [] : ["no component {$this->namespace}\\{$name}"]), "no {$kebab}.sharp.php under resources/"];
        throw new ViewException("Unknown tag <{$name}>: not a built-in (".implode(', ', array_keys(self::ELEMENTS)).'), '.implode(', ', $looked));
    }

    /**
     * Every sharp file, by path under resources/.
     *
     * @return list<string>
     */
    public function all(): array
    {
        return array_merge(...array_values($this->templates()));
    }

    public static function kebab(string $name): string
    {
        return strtolower(preg_replace('/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', '-', $name));
    }

    private function component(string $name): ?ReflectionClass
    {
        $class = is_null($this->namespace) ? null : "{$this->namespace}\\{$name}";
        if (is_null($class) || ! class_exists($class)) {
            return null;
        }

        $found = new ReflectionClass($class);

        return $found->isSubclassOf(Component::class) && ! $found->isAbstract() && $found->getShortName() === $name ? $found : null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function templates(): array
    {
        if (! is_null($this->templates)) {
            return $this->templates;
        }

        $this->templates = [];
        if (is_dir($this->resources)) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->resources, FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.sharp.php')) {
                    $this->templates[basename($file->getFilename(), '.sharp.php')][] = substr($file->getPathname(), strlen($this->resources) + 1);
                }
            }
            array_walk($this->templates, static function (array &$paths): void {
                sort($paths);
            });
        }

        return $this->templates;
    }
}
