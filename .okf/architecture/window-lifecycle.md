---
type: Module
title: Window lifecycle
description: What every toolkit window does the same way - the one close path, confirm-close, close() from code, resize mail, the after-pump settle - and the hooks an adapter fills in.
resource: src/Surface/Toolkits/ToolkitWindow.php
tags: [topside, toolkits, windows]
status: draft
generated: { by: claude-opus/5.5, at: 2026-11-21T20:30:00Z }
verified:
  - { by: claude-opus/5.5, at: 2026-11-20T21:20:00Z }
sources:
  - id: window
    resource: src/Surface/Toolkits/ToolkitWindow.php
    title: ToolkitWindow
  - id: contract
    resource: src/Surface/Contracts/Toolkits/ToolkitPoweredWindow.php
    title: ToolkitPoweredWindow
---

# Contract

`ToolkitPoweredWindow`: `name()`, `present()`, `setTitle()`, `native()` (the toolkit's own window object or handle), `size()` (the content area), `isOpen()`, `post($mail)`, `close()`, `confirmClose($on)`, `column()` (makes the content container, once), `content()`, `primitiveFor($contract)`, `setInitialFocus()`, `focused()`, and `contentRemoved()` (called by the content container's `remove()`).[^contract]

# Base class

`ToolkitWindow` builds the native window through `bootstrapWindow()` in its constructor, then registers itself with the [session](/architecture/adapters.md) for the after-pump `settle()`.[^window]

| Situation | Path |
|---|---|
| the user closes the window (title bar, Cmd+W) | the adapter's native signal asks `allowsUserClose()`: while confirming (and not inside `close()`) it posts `WindowCloseRequested` and answers false, so the window stays; else the adapter runs `closed()` |
| code closes it | `close()` sets `$closing` and calls the adapter's `closeNative()`, whose native signal reaches `closed()`; a confirming window closes too |
| the native window is gone | `closed()`, the one close path, safe to call twice: `beforeClose()`, the content tree removed, the session stops tracking the window (dropping a resize not yet sent), `releaseNative()`, `WindowClosed` posted, the driver forgets the name |
| the content area changed size | `resized($w, $h)`: one `WindowResized` per pump, the last size |
| after every pump | `settle()`, empty unless the adapter compares something there; then `settleFocus()`: the window's focus is the deepest shown primitive whose `hasFocus()` says so, and a change posts `FocusLost` (old, unless removed) then `FocusGained` (new) |
| `present()` | the adapter shows the window, then `focusInitial()`: the `setInitialFocus()` primitive takes the focus through its own `focus()` |

# Adapter hooks

`bootstrapWindow()`, `isOpen()`, `closeNative()`, `releaseNative()`, `mountContent()` / `unmountContent()` (the content column's native into and out of the content area); optional `beforeClose()` and `settle()`. Plus the contract's `present()`, `setTitle()`, `native()`, `size()`.

# Checked

On AppKit, Qt, GTK and Raven (2026-11-20): open, resize from code, confirm-close (the user's close posts `WindowCloseRequested` and the window stays), `close()` from code (`WindowClosed`, `isOpen()` false, the name reusable). Focus (2026-11-21, real-window harness, 42 checks per toolkit): initial focus and its `FocusGained`, `focus()` between two buttons posting `FocusLost` then `FocusGained`, no mail while focus stays, hiding the focused button's row, removing a focused primitive.

[^window]: ToolkitWindow
[^contract]: ToolkitPoweredWindow
