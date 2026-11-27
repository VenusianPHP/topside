---
type: Module
title: Toolkit adapters
description: How a toolkit package plugs in - a provider that registers without building, a driver that makes the session and declares its window class, a session that pumps and settles windows.
resource: src/Surface/Toolkits/
tags: [topside, toolkits, adapters]
status: draft
generated: { by: claude-opus/5.5, at: 2026-11-26T05:40:00Z }
sources:
  - id: provider
    resource: src/Surface/Toolkits/ToolkitAdapterServiceProvider.php
    title: ToolkitAdapterServiceProvider
  - id: driver
    resource: src/Surface/Toolkits/Drivers/ToolkitAdapterDriver.php
    title: ToolkitAdapterDriver
  - id: session
    resource: src/Surface/Toolkits/ToolkitSession.php
    title: ToolkitSession
  - id: toolkit
    resource: src/Surface/Contracts/Toolkits/Toolkit.php
    title: Toolkit contract
---

# The four pieces

A toolkit package (`jovian/venusian-appkit`, `-qt`, `-gtk`, `-raven`) supplies four classes. Topside owns everything they share.

| Piece | Topside base | The package supplies |
|---|---|---|
| Provider | `ToolkitAdapterServiceProvider` | three properties: `$toolkit` (the name `toolkits.default` selects), `$driver` (class), `$config` (its config file) |
| Driver | `Drivers\ToolkitAdapterDriver` | the extension checks in its constructor, `instanceSession()`, `protected string $window` (its window class) and `protected array $primitives` (contract => class: the one place a toolkit names its column, row, fixed, label and button); `registerPrimitive($contract, $class)` replaces or adds one, refusing a class that does not implement the contract |
| Session | `ToolkitSession` | `bootstrapToolkitLibrary()`: build the toolkit object once |
| Toolkit | `Contracts\Toolkits\Toolkit` | `connect()`, `disconnect()`, `pump($budget_ns)`, `wakeDescriptor($fd)` |

The window is the fifth: see [window lifecycle](/architecture/window-lifecycle.md).

# Provider

`boot()` publishes and merges the package's config under `$toolkit`, then `ToolkitAdapters::extend($toolkit, fn ($app) => new $driver($app))`. It builds no driver: the manager builds one the first time `toolkits.default` (or a direct `ToolkitAdapters::driver()`) asks for it. So an installed toolkit whose extension or OS is missing does nothing until selected; one app can require AppKit and GTK and run whichever its platform has.[^provider]

# Driver

`open($name, $width, $height, $parent)` refuses a name already open, connects the session (`instanceSession()` then `session->connect()`), and builds `new $this->window($name, $session, $this, $width, $height, $parent)` (`conjureWindow()`, which a driver may override). `forget($name)` drops a closed window. `primitiveFor($contract)` reads the primitive map; containers and the window's content column are built from it.[^driver]

# Session

* `connect()` builds the toolkit once per process, connects it, and joins the event loop when the app runs.
* Right after the toolkit is built, `applyColorScheme(ColorScheme)` gets `toolkits.color_scheme` (`colorScheme()` reads it). Optional: an adapter overrides it with its toolkit's app-wide light/dark setting; the default does nothing, which is the system's scheme.
* `joinLoop()` hands the loop's descriptor to `wakeDescriptor()`, registers a `ToolkitPump` as the loop's sleeper (the toolkit's wait becomes the loop's sleep), and delivers mail posted before the join.
* `pump($ns)` calls the toolkit's `pump()`, then `settle()` on every open window: what a toolkit has no callback for (GTK's resizes, Raven's activation) is compared there.
* `post()`, `postLatest($key)` (latest per pump), `forgetLatest($key)`, `flushLatest()`; `resizedKey($window)` is the one key resize mail collapses under.
* `watchWindow()` / `unwatchWindow()` are called by the window itself.
* On macOS one toolkit session at a time: a second toolkit's `connect()` is refused while another is connected.[^session]

[^provider]: ToolkitAdapterServiceProvider
[^driver]: ToolkitAdapterDriver
[^session]: ToolkitSession

## A primitive from another package

A package that builds on a toolkit adds a kind of primitive to it without the toolkit's package knowing: its provider calls `ToolkitAdapters::primitive($toolkit, $contract, $class)`. `ToolkitAdapterManager` keeps it by toolkit name and hands it to that toolkit's driver through `registerPrimitive()` when the driver is made, or at once when it already is; a class that does not implement the contract is refused. A toolkit that has no class for a contract the group makes throws when one is asked for, so a package giving one is how a toolkit's own framework split out of its extension (AVKit beside AppKit) reaches its primitives.
