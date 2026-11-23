# Venusian Topside

[![Tests](https://github.com/VenusianPHP/topside/actions/workflows/tests.yml/badge.svg)](https://github.com/VenusianPHP/topside/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/venusian/topside.svg)](https://packagist.org/packages/venusian/topside)
[![Total Downloads](https://img.shields.io/packagist/dt/venusian/topside.svg)](https://packagist.org/packages/venusian/topside)
[![License](https://img.shields.io/packagist/l/venusian/topside.svg)](LICENSE)

## About Venusian Topside

> **Note:** This repository contains the visual output package for the Venusian framework. If you want to build an application using Venusian, visit the main [Venusian skeleton repository](https://github.com/VenusianPHP/venusian).

Venusian Topside is the Venusian Visual Output Framework: windows, native widgets and reactive views for building desktop applications, using a Laravel-like expressive syntax and familiar components.

Like building desktop apps? Saw those tutorials that use Electron or Swift and just didn't feel like learning a whole new language for the whole ordeal? Well look no further!

Venusian Topside doesn't drift very far from Laravel's expressive, elegant syntax. Map a route to a controller, return a view with `View::render()`, and write that view as a PHP component or as a Sharp template, where markup, mustaches and PHP live together. There's simply no reason that if JavaScript can do it, PHP can't! And where PHP needed extending to reach native toolkits, [we did that, too](https://github.com/orgs/php-io-extensions).

Venusian Topside will take the pain out of building windows full of buttons, inputs, tabs, tables, trees and other native widgets. Write a view once and it runs on AppKit, Qt, GTK or Raven, through the `jovian/venusian-appkit`, `jovian/venusian-qt`, `jovian/venusian-gtk` and `jovian/venusian-raven` adapters.

Got a Mac? All four toolkits have been tested there. AppKit is macOS only, and Qt, GTK and Raven have yet to be tested with Topside on Linux. Whichever you pick, you can make PHP build desktop experiences for an extremely satisfying experience!

> **Built with AI assistance.** This package was developed LLM-assisted — not vibe-coded. Generated code was deliberately prompted and reviewed by a professional software engineer. See [AI-assisted development](#ai-assisted-development).

## Learning Venusian Topside

Venusian has documentation and guides on the [Venusian website](https://venusian.projectsaturnstudios.com/docs), making it a breeze to get started with the framework Topside is built on.

## Syntax highlighting

Topside ships a TextMate grammar for Sharp views (`*.sharp.php`) in `editors/sharp`. It colors tags, components, attributes, directives, comments and the PHP inside them. It does not add completion or error checking.

### Cursor

From your application's root, build the extension and install it:

```bash
php vendor/venusian/topside/editors/sharp/build.php
cursor --install-extension vendor/venusian/topside/editors/sharp/sharp-0.1.0.vsix --force
```

- The build needs PHP's `zip` extension.
- If `cursor` isn't on your `PATH`, on macOS use `/Applications/Cursor.app/Contents/Resources/app/bin/cursor`.
- Run both commands again after updating Topside to pick up new built-in tags.

### PhpStorm

1. In **Settings > Editor > TextMate Bundles**, add the `vendor/venusian/topside/editors/sharp` folder.
2. In **Settings > Editor > File Types**, select **Text** under **Recognized File Types**.
3. Under **File name patterns**, add `*.sharp.php`, then apply.

Select **Text**, even though these are PHP files: PhpStorm otherwise opens them as PHP and never uses the bundle.

Because of that, Sharp files lose PhpStorm's PHP completion and inspections, including inside `@script`, and the PHP in them won't match the colors of your other PHP files.

## AI-assisted development

This codebase was built with LLM assistance under human direction. Prompts, reviews, and architectural decisions were owned by a professional software engineer — the model accelerates drafting and exploration; it does not replace judgment on APIs, dependency direction, or what ships.

## Contributing

Thank you for considering contributing to Venusian Topside! The contribution guide can be found in the [Venusian documentation](https://venusian.projectsaturnstudios.com/docs/contributions).

## Code of Conduct

In order to ensure that the Venusian community is welcoming to all, please review and abide by the [Code of Conduct](https://venusian.projectsaturnstudios.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

Please review [our security policy](https://github.com/VenusianPHP/topside/security/policy) on how to report security vulnerabilities.

## License

Venusian Topside is open-sourced software licensed under the [MIT license](LICENSE).
