---
type: Module
title: Menu definitions
description: The values an author writes a menu bar with - MenuBar, Menu, Item, Toggle, Choice, Separator, standard items, Shortcut - their rules, transforms, mail names, and the profiles in config.
resource: src/Surface/Toolkits/Menus/
tags: [topside, toolkits, menus]
status: draft
generated: { by: claude-opus/5.5, at: 2026-11-22T19:07:34Z }
sources:
  - id: node
    resource: src/Surface/Toolkits/Menus/Node.php
    title: Node, CopiesItself
  - id: menu
    resource: src/Surface/Toolkits/Menus/Menu.php
    title: Menu
  - id: bar
    resource: src/Surface/Toolkits/Menus/MenuBar.php
    title: MenuBar
  - id: standard
    resource: src/Surface/Toolkits/Menus/StandardItem.php
    title: StandardItem, Standard, About
  - id: shortcut
    resource: src/Surface/Toolkits/Menus/Shortcut.php
    title: Shortcut
  - id: manager
    resource: src/Surface/Toolkits/Managers/MenuManager.php
    title: MenuManager
  - id: mail
    resource: src/Surface/Contracts/Toolkits/Mail/Menus/
    title: Menu mail
  - id: config-cache
    resource: ../framework/src/Voyager/Core/Console/ConfigCacheCommand.php
    title: ConfigCacheCommand (var_export)
  - id: dispatcher
    resource: ../framework/src/Voyager/Signals/SignalDispatcher.php
    title: SignalDispatcher (wildcard listeners)
  - id: tests
    resource: tests/Unit/Menus/
    title: Menu tests
---

# What is built

Stage 1 of the [menu bar design](/design/menus.md): the values a bar is written with, the profiles that name them, and the mail classes. What shows a bar and posts the mail is the [menu runtime](/architecture/menu-runtime.md). These values involve no window, toolkit or loop.[^tests] The rules and their reasons are in the design; this says where each lives in the code.

# Classes

All in `Surface\Toolkits\Menus\`, `final readonly`, checked in the constructor. `CopiesItself::with()` builds every copy through the constructor, so a copy is checked as the original was, and gives `__set_state()` for `config:cache`.[^node][^config-cache]

| Class | Built with | Copies |
|---|---|---|
| `MenuBar` | `make(Menu ...)` | `with()`, `without()`, `extend()`, `item()`, `before()`, `after()` |
| `Menu` | `make(title, [id,] nodes...)`; `application(nodes...)`, id `app` | `titled()`, `enabled()`, `append()`, `prepend()`, `before()`, `after()`, `without()`, `replace()` |
| `Item` | `make(label, id:, onClick:)` | `onClick()`, `shortcut()`, `enabled()`, `label()` |
| `Toggle` | `make(label, on:, id:, onToggle:)` | `on()`, `onToggle()`, `shortcut()`, `enabled()`, `label()` |
| `Choice` | `make(options, chosen, id:, onChoose:)` | `chosen()`, `onChoose()`, `enabled()` |
| `Separator` | `make()` | |
| `StandardItem` | `Standard::*()`, `About::panel()`, `About::item(id: or onClick:)` | `label()`, `shortcut()` (null takes it off), `enabled()`, `id()`, `onClick()` |
| `Shortcut` | `primary(key)`, `key(a key that stands alone)`, `of('Alt+S')` | `shift()`, `alt()`, `ctrl()` |

`Item`, `Toggle`, `Choice`, `About::item()` and `Standard::preferences()` refuse to be built with neither id nor callback. `About::opens()` is stage 4.

# Ids, keys and paths

* An id is lower case letters, digits and `-`. No two nodes of one menu share one.[^menu]
* A menu's key is its id, else its title. Any other node's key is its id, else its standard kind, else its label (a choice: its option values); a separator has none. No two nodes of one menu share a key.[^menu]
* A menu with an item with an id anywhere below it has an id, checked as each menu is built.[^menu]
* `MenuBar::paths()` is every item with an id by its path, `file.export.pdf`; `find()` and `item()` take that path, and mail is named by it.[^bar]

# What is refused, and when

| When | Refused |
|---|---|
| a value is built | no label or title, a bad id, nothing to do, a choice with no valid option chosen, a key that does not stand alone (`ShortcutKey::standsAlone()`) with no Primary, Ctrl or Alt; one that does yields to the focused widget (`Shortcut::yieldsToFocus()`) |
| a menu or bar is built or copied | a missing menu id, a shared id or key, a second application menu, an application menu inside a menu |
| a change | `extend()`, `item()`, `before()`, `after()`, `replace()` of what is not there. `without()` of it does nothing; `menu()` and `find()` give null |
| `MenuBar::on($mac)` | two nodes with one shortcut on that platform.[^shortcut] The same call leaves out hide, hide others and show all off macOS |
| a profile is read | not a `MenuBar`, or it holds a callback[^manager] |

An exception that leaves a signal listener leaves the event loop's `run()` (probe 2026-11-22); where topside catches a bad menu is in the [menu runtime](/architecture/menu-runtime.md).

# Standard items

`StandardItem::labelOn($mac, $app)` and `shortcutOn($mac)` give what a platform shows: the override, else the platform's own. `isReplaced()` is true once it has an id or a callback. `StandardKind` says which kinds act on text, on the window, or exist only on macOS.[^standard]

# Profiles

`toolkits.windows.menus` holds them and `toolkits.windows.default_menu` names the default; topside ships none. `Menus::profile($name)` reads one through `MenuManager` (`toolkit-menus`). A method is called in the config file itself, so what is cached is the bar it returned. Checked through the real `config:cache` command on NTS and ZTS, 2026-11-22.[^manager]

# Mail

`Surface\Contracts\Toolkits\Mail\Menus\`: `MenuItemClicked` (`menu.clicked.{path}`), `MenuItemToggled` (`menu.toggled.{path}`, `on`), `MenuOptionChosen` (`menu.chosen.{path}`, `option`); each has `window()` and `path()`.[^mail] Wildcards hear a whole menu: `menu.clicked.file.*`.[^dispatcher]

[^node]: Node, CopiesItself
[^menu]: Menu
[^bar]: MenuBar
[^standard]: StandardItem, Standard, About
[^shortcut]: Shortcut
[^manager]: MenuManager
[^mail]: Menu mail
[^config-cache]: ConfigCacheCommand (var_export)
[^dispatcher]: SignalDispatcher (wildcard listeners)
[^tests]: Menu tests
