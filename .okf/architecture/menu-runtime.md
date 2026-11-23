---
type: Module
title: Menu runtime
description: How a window's bar is worked out, shown and answered - MenuManager, the planner and mounter, TKMenuBar, what an adapter implements, and where a bad menu is caught.
resource: src/Surface/Toolkits/Menus/Runtime/
tags: [topside, toolkits, menus, runtime]
status: draft
generated: { by: claude-fable/5.2, at: 2026-11-22T23:05:00Z }
sources:
  - id: manager
    resource: src/Surface/Toolkits/Managers/MenuManager.php
    title: MenuManager
  - id: runtime
    resource: src/Surface/Toolkits/Menus/Runtime/
    title: MenuPlanner, MenuMounter, MenuBlock
  - id: contract
    resource: src/Surface/Contracts/Toolkits/Menus/TKMenuBar.php
    title: TKMenuBar, BarNode
  - id: compiler
    resource: src/Surface/Toolkits/Views/Sharp/MenuCompiler.php
    title: MenuCompiler
  - id: tests
    resource: tests/Unit/Menus/
    title: Menu tests
---

# What is built

Stage 2a of the [menu bar design](/design/menus.md), against the fake toolkit.[^tests] All four adapters have a bar class (stage 2b): AppKit, Qt, GTK and Raven.

# The path of a bar

1. `MenuManager` keeps a `WindowMenu` per open window and works out the four steps: the window's, the route's, `Menus::page()`, the view's `menu()`.[^manager]
2. `MenuPlanner` makes that a plan for the platform: filters, shortcut check, application menu first (an empty one on macOS when a bar has none; no bar at all stays none), labels, effective enablement, the user's marks.[^runtime]
3. `MenuMounter` tells the `TKMenuBar` single operations. A node is matched by key among its siblings; `BarNode::sameShape()` says when it is made again. An unchanged bar costs none. When the toolkit fails partway it puts the last good plan back.[^runtime]

Worked out when: a window opens, `Menus::window()` or `page()`, a route has opened (once, as the controller returns), a view is rendered or re-rendered; then every window sharing that bar. Not on focus.

# The path of a click

1. The adapter's native signal calls `ToolkitMenuBar::activated(uuid, value)`. A toggle's or choice's mark changes there and then, and `MenuNodeActivated` is posted through the session.
2. In a loop turn `LoadSignals` hands it to `MenuManager::deliver()`: the callback runs, the owner's view renders again unless it left or its window closed, then the public mail is posted. An unreplaced Quit posts `QuitRequested`. A node gone by then is dropped.
3. `MenuMail` reaches `heard()`: the `#[OnMenu]` methods of views on screen, and `Menus::on()` listeners, which end at the window's next route.

`#[OnMenu]` and `Menus::on()` go by path alone, whichever window posted; the mail's `window()` tells them apart.

# Sharing and marks

* `Menus::sharedWith()` starts step 1 from that window's finished bar. Callbacks re-render that window's view, and marks are its marks. When that window closes a sharer keeps the last bar, and follows again if one of the name opens.
* A mark lasts while the definition says what it said when the user chose. Through the node's absence it lasts only for a node with an id and no callback.
* The bar shown on macOS with no window focused has marks of its own.

# A bad menu

Topside catches where it runs menu code, and nowhere else:

| Where | Caught |
|---|---|
| working out or showing a bar (steps, `menu()`, `@menu`, the toolkit's operations) | every failure: reported, last good bar kept |
| a route's controller, a view's handler, a menu callback, `#[OnMenu]`, `Menus::on()` | `MenuException` only: reported. Anything else is the application's |
| an application's timers and own signal listeners | nothing: they are its own code. The framework knows nothing of topside and gets no hook for it (decided by Angel, 2026-11-22) |

A report goes to the exception handler as a `MenuException` naming the window, the cause as its previous. One standing failure is reported once until the bar works again.[^manager]

# What an adapter implements

* A class extending `Runtime\ToolkitMenuBar`: the seven `TKMenuBar` methods, and `activated()` from the native signal. It reads no definition: a `BarNode` is already worked out. `insert()` gives a menu before its contents; a `CHOICE` is one node with a native per option; a shortcut whose `yieldsToFocus()` is true goes to the focused widget first; the toolkit does an unreplaced standard item's work itself, but Quit.[^contract]
* Its driver names it in `$menu_bar`.
* Its window calls `gainedFocus()` and `lostFocus()` inside the toolkit's own focus callbacks, not from mail.

# Sharp

`Source` takes `@menu` or `@menu replace` apart; `MenuCompiler` writes `menu()` with the template's line numbers, its vocabulary read off the definitions' constructors and separate from the elements' (`<Toggle>`, `<Separator>` are in both). At render `MenuBlock` lays the tags over the bar; its errors name the line.[^compiler] The editor grammar does not colour the block yet.

[^manager]: MenuManager
[^runtime]: MenuPlanner, MenuMounter, MenuBlock
[^contract]: TKMenuBar, BarNode
[^compiler]: MenuCompiler
[^tests]: Menu tests
