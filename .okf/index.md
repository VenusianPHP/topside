---
okf_version: "0.2"
---

# venusian/topside

* [Toolkit adapters](architecture/adapters.md) - How a toolkit package plugs in - a provider that registers without building, a driver that makes the session and declares its window class, a session that pumps and settles windows.
* [Window lifecycle](architecture/window-lifecycle.md) - What every toolkit window does the same way - the one close path, confirm-close, close() from code, resize mail, the after-pump settle - and the hooks an adapter fills in.
* [Primitives](architecture/primitives.md) - The TK primitive classes - state and rules shared by every toolkit, the factories, column/row/fixed layout, label and button - and the hooks each adapter fills in.
* [Views](architecture/views.md) - Components that describe a window's content from their state, and the Mounter that turns those descriptions into primitives and changes only what differs after each handler.
* [Sharp](architecture/sharp.md) - Markup views - resources/**/*.sharp.php compiled to Component classes, sharing the Mounter and usable as tags.
* [Menu definitions](architecture/menus.md) - The values a menu bar is written with - MenuBar, Menu, Item, Toggle, Choice, standard items, Shortcut - their rules and transforms, menu mail, and the profiles in config.
* [Menu runtime](architecture/menu-runtime.md) - How a window's bar is worked out, shown and answered, what an adapter implements, and where a bad menu is caught. All four adapters have a bar.
* [Mail](architecture/mail.md) - The signals toolkit windows and primitives post, their names, and who posts each.

# Design

* [Menu bars](design/menus.md) - The design for menu bars, approved 2026-11-22 - definition DTOs, the runtime that shows them, how a window's bar is chosen, standard items, mail, the build stages, and the approved proposals.

# API

* [Config](api/config.md) - config/toolkits.php and config/presentation.php keys - which toolkit runs, the first window's size.
