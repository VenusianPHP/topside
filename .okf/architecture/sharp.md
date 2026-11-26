---
type: Module
title: Sharp
description: Markup views - resources/**/*.sharp.php compiled to Component classes, so templates and composed components share one Mounter and use each other as tags.
resource: src/Surface/Toolkits/Views/Sharp/
tags: [topside, toolkits, views, sharp, templates]
status: draft
generated: { by: claude-opus/5.5, at: 2026-11-25T20:34:09Z }
sources:
  - id: sharp
    resource: src/Surface/Toolkits/Views/Sharp.php
    title: Sharp
  - id: compiler
    resource: src/Surface/Toolkits/Views/Sharp/Compiler.php
    title: Compiler
  - id: resolver
    resource: src/Surface/Toolkits/Views/Sharp/TagResolver.php
    title: TagResolver
  - id: computer
    resource: src/Surface/Core/Providers/ComputerServiceProvider.php
    title: ComputerServiceProvider
---

# The file

`View::render('demo.index')` is `resources/demo/index.sharp.php`; a Component class name is checked first. `@script` ... `@endscript` holds properties and methods; `use` lines before its first member are the file's imports (so a trait is used further down). `@template` ... `@endtemplate` holds one root tag. `@menu` (or `@menu replace`) ... `@endmenu` is optional and becomes `menu()`, with tags of its own: see [Menu runtime](/architecture/menu-runtime.md). `@style` is refused.[^sharp]

# Markup

* `name="text"` is cast to the type the element takes; `:name="expr"` is PHP; a name alone is true. Several arguments are spaced: `minSize="340 0"`, `align="start center"`. `font="15 bold italic"`, `background="#33669980"`. `padding` and `margin` are one to four values in CSS order: `padding="40 16"`, `margin="12 0 8"`, checked when compiled. `cursor="pointer"`, `border="1 #33333380"`, `style="link"`, `role="default"`. `cornerRadius` is one to four radii in CSS `border-radius` order (`cornerRadius="0 0 14 14"`), checked when compiled; `clip` alone cuts the content at the edge. `maxSize="768 0"` is the most an element grows to, 0 for no limit that way; with `fill` and `align="center"` it gives a centred column. `iconPosition="trailing"` puts a button's icon after its label. `homogeneous` on a `<Row>` or `<Column>` gives every child the same length along its axis. Inside `<Label>`, `<b>`, `<i>` and `<span color="#rrggbb">` (or `:color`) make runs, nested or not and with `{{ }}` inside; white space is one space and the ends are trimmed, as for plain text, and any other tag is an error. `<Overlay>` holds its children on top of each other, the first at the back. `appear="move-right"` and `disappear="move-right 0.4"` take a transition and, optionally, seconds; a primitive built hidden takes no transition, and one already there plays it each time `visible` changes. An element inserted into a view already on screen (a drawer pushed onto a stack) plays its `appear` as it is built (`TKPrimitive::playAppear()`); a view's first render plays nothing.
* A text tag's content is its text; `{{ expr }}` works there and in text attributes. `@{{` and `@@` are literal.
* `@click` works on `<Button>` and on every container (`<Column>`, `<Row>`, `<Grid>`, `<Overlay>` and the rest), which then listens for clicks (`setClickable()`); a label or image alone has none. `<WebView url="…">` or `<WebView :html="$page" :baseUrl="$origin">` loads again only when the page changes; `<ScrollView @scroll="fn ($at) => ...">` is told where the view is as the user scrolls (`ScrollPosition`: `$at->y + $at->height` is 1.0 at the bottom) and watches only while it has the handler. `@loaded` and `@failed` take `WebViewLoaded` and `WebViewFailed` (also on a toolkit with no web engine). `<Button padding="0 16">` pads a button, one to four values. `@click="save"`, `@click="remove($id)"`, `@change="fn ($v) => $this->name = $v"`. Template variables are copies of the public properties: assigning one in a handler is a compile error.
* `@if/@elseif/@else/@endif`, `@foreach/@endforeach` (each element in it has a key), `@php/@endphp`, `{{-- --}}`, `<Children/>`.
* The vocabulary is read off the element classes: make() arguments, fluent setters, on*() methods. A new element is one line in `TagResolver::ELEMENTS`, and one in `Compiler::CONTENT` if it has text content.[^compiler]

# Tags

A built-in name is reserved. Otherwise `<ImageCarousel>` is `App\Views\ImageCarousel` or a file `image-carousel.sharp.php` anywhere under resources/; more than one claim is a compile error. A component's public properties are its attributes; other attributes are settings laid over its root. `@closed` sets its `onClosed` closure property. Composition uses a template with `View::component('name', prop: ...)`.[^resolver]

# Compiling

Each file becomes a trait (the script) and a class using it, in `storage/framework/views`, both with the template's line numbers. A record holds the hashes of what it was compiled from and what each tag meant. `View::render()` looks at the files again and loads every template the view's markup can reach; re-renders read no file. `php computer sharp:cache` compiles all. The command comes with venusian/topside, not with the toolkits component: it is `Surface\Core\Console\SharpCacheCommand`, registered by `Surface\Core\Providers\ComputerServiceProvider`, as the framework's Core does for its components. A new Topside command is a class there and a line in that provider's `$commands`, which binds it as well as registering it: the console's lazy loader lists only bound classes.[^sharp][^computer]

# In an editor

`editors/sharp/` is a TextMate grammar packed as a Cursor / VS Code extension, and usable by PhpStorm as a TextMate bundle. PHP inside is the editor's own `source.php`; `@endscript`, a closing quote and `}}` end it even when that PHP is unfinished. It colours; it does not complete.

* Cursor: `php editors/sharp/build.php`, then `cursor --install-extension editors/sharp/sharp-0.1.0.vsix --force` (the vsix is not committed, and its version does not change with the tags). The steps for an application's developer, from `vendor/`, are in `README.md`.
* PhpStorm: Settings > Editor > TextMate Bundles, add `editors/sharp`; then Settings > Editor > File Types, select **Text** and add `*.sharp.php` to its file name patterns. Not a TextMate type: that one is read-only, so the page never lists it. A bundle takes over a file only when its name maps to Text, TextMate or no type at all, and `*.php` maps to PHP until the pattern says otherwise.

The built-in tag names are written in the grammar: `tests/Unit/Editors/SharpGrammarTest.php` fails when `TagResolver::ELEMENTS` and the grammar differ. Checked 2026-11-21 on Cursor's tokenizer with both editors' PHP grammars (147 checks), an install in a throwaway Cursor profile, and by running PhpStorm 2026.1.1's own association table, bundle reader and lexer on the demo view (pattern wins over `*.php`, bundle chosen, 59 of 59 tags); the colours were not looked at in the IDE. PHP inside does not get PhpStorm's own PHP colours: its TextMate plugin maps scopes to about 35 generic keys, not to the PHP highlighter's (found in its bytecode, 2026-11-21). That, and completion, take a real plugin.

[^sharp]: Sharp
[^compiler]: Compiler
[^resolver]: TagResolver
[^computer]: ComputerServiceProvider
