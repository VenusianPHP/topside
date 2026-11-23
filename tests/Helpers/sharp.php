<?php

declare(strict_types=1);

use Surface\Toolkits\View;
use Surface\Toolkits\Presentation;
use Surface\Toolkits\Views\Sharp;

/*
| An application's resources/ and cache in a folder of the test's own, with App\Views standing
| in as the fakes' namespace.
*/
function sharpApp(): void
{
    test()->root = sys_get_temp_dir().'/sharp-'.bin2hex(random_bytes(6));
    mkdir(test()->root.'/resources', 0755, true);
    test()->app->registerInstance(Sharp::class, newSharp());
}

function newSharp(): Sharp
{
    return new Sharp(test()->root.'/resources', test()->root.'/cache', 'Venusian\\Topside\\Tests\\Fakes\\Views\\App');
}

function dropSharpApp(): void
{
    if (! is_dir(test()->root)) {
        return;
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(test()->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir(test()->root);
}

/** Write resources/$name.sharp.php, dots as folders. */
function template(string $name, string $content): void
{
    $path = test()->root.'/resources/'.str_replace('.', '/', $name).'.sharp.php';
    is_dir(dirname($path)) || mkdir(dirname($path), 0755, true);
    file_put_contents($path, $content);
}

/** Write a template of $markup and $script, and put it on screen. */
function sharp(string $markup, string $script = '', array $props = []): object
{
    template('page', ($script === '' ? '' : "@script\n{$script}\n@endscript\n")."@template\n{$markup}\n@endtemplate\n");
    View::render('page', $props);

    return Presentation::driver()->view()->component;
}

/** What is on screen, down to every hook each primitive was told, without uuids. */
function screenShape(mixed $at = null): array
{
    $at ??= screen();
    $children = method_exists($at, 'children') ? $at->children() : [];

    return array_map(fn ($child) => [
        $child->name(),
        $child::class,
        method_exists($child, 'text') ? $child->text() : null,
        ! method_exists($child, 'children') && method_exists($child, 'label') ? $child->label() : null,
        serialize($child->calls),
        screenShape($child),
    ], $children);
}
