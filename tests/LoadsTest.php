<?php

declare(strict_types=1);

/*
 * Every class, interface, trait and enum in src/ loads: PHP checks a class against what it
 * extends and implements only as it loads it, and php -l does not. A class no other test reaches
 * would otherwise carry an incompatible signature unnoticed.
 */

dataset('declarations', function (): array {
    $src = dirname(__DIR__).'/src';
    $found = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $code = (string) file_get_contents($file->getPathname());
        if (preg_match('/^namespace\s+([^;]+);/m', $code, $ns) !== 1
            || preg_match('/^(?:(?:abstract|final|readonly)\s+)*(?:class|interface|trait|enum)\s+(\w+)/m', $code, $name) !== 1) {
            continue;
        }
        $fqcn = $ns[1].'\\'.$name[1];
        $found[$fqcn] = [$fqcn];
    }
    ksort($found);

    return $found;
});

it('loads', function (string $declaration) {
    try {
        $loaded = class_exists($declaration) || interface_exists($declaration) || trait_exists($declaration) || enum_exists($declaration);
    } catch (Error $e) {
        // topside's own vendor installs only part of the framework: a parent from the rest is the
        // application's to install, and loads there (an application's boot loads these classes)
        if (preg_match('/^(?:Class|Interface|Trait) "(Voyager\\\\[^"]+)" not found$/', $e->getMessage(), $missing) === 1) {
            $this->markTestSkipped("{$missing[1]} is a framework class this package's vendor does not install.");
        }
        throw $e;
    }

    expect($loaded)->toBeTrue();
})->with('declarations');
