---
type: Reference
title: Config
description: config/toolkits.php and config/presentation.php keys - which toolkit runs, the first window's size.
resource: config/toolkits.php
tags: [topside, config]
status: draft
generated: { by: claude-opus/5.5, at: 2026-11-22T19:07:34Z }
sources:
  - id: toolkits
    resource: config/toolkits.php
    title: config/toolkits.php
  - id: presentation
    resource: config/presentation.php
    title: config/presentation.php
---

# Schema

Merged by `TopsideServiceProvider`: `toolkits` from `config/toolkits.php`, `main.presentation` from `config/presentation.php`; published under the tag `surface-config`.[^toolkits]

| Key | Default | Meaning |
|---|---|---|
| `toolkits.default` | `env('TOOLKIT_DRIVER', 'null')` | the toolkit that runs: a name an adapter's provider registered (`appkit`, `qt`, `gtk`, `raven`); `null` refuses ("No default toolkit set") |
| `toolkits.windows.default_menu` | `null` | the name of the menu profile a window shows when nothing gives it another; null for none (no adapter shows a bar yet) |
| `toolkits.windows.menus` | `[]` | menu profiles by name: each a `MenuBar` with no callbacks; see [Menu definitions](/architecture/menus.md) |
| `main.presentation.mode` | `toolkit` | the build the app opens |
| `main.presentation.window_size` | `[1024, 678]` | the first window's size |

[^toolkits]: config/toolkits.php
