<?php

declare(strict_types=1);

use Surface\Toolkits\Views\Sharp\TagResolver;

it('colours every built-in tag of Sharp as a built-in', function (): void {
    $grammar = json_decode(file_get_contents(dirname(__DIR__, 3).'/editors/sharp/syntaxes/sharp.tmLanguage.json'), true, flags: JSON_THROW_ON_ERROR);

    preg_match('/^\(<\/\)\(([A-Za-z|]+)\)/', $grammar['repository']['tag']['patterns'][0]['match'], $found);

    expect(explode('|', $found[1] ?? ''))->toEqualCanonicalizing([...array_keys(TagResolver::ELEMENTS), ...array_keys(TagResolver::COMPONENTS)]);
});
