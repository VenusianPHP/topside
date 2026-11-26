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

`ToolkitPoweredWindow`: `name()`, `present()`, `setTitle()`, `native()` (the toolkit's own window object or handle), `size()` (the content area), `isOpen()`, `post($mail)`, `close()`, `confirmClose($on)`, `column()` (makes the content container, once), `content()`, `primitiveFor($contract)`, `setInitialFocus()`, `focused()`, `contentRemoved()` (called by the content container's `remove()`), and `alert(Alert)` / `isAlerting()`, and `browse($url)`: an http or https address with a host (anything else throws `InvalidArgumentException`) opened in the default browser by the adapter's `openUrl()`, the toolkit's own call (AppKit `NSWorkspace::openURL`, Qt `QDesktopServices::openUrl`, GTK `GtkUriLauncher::launch`, Raven `Ropen_url`); the bridge-era `Desktop::browse()`, on the window so GTK gets its parent.[^contract]

# Alerts

`alert(Alert $alert): string` shows the toolkit's own alert, modal to the window, and returns an id at once; one alert at a time (a second, or one on a closed window, throws). `Alert` (`Surface\Contracts\Toolkits\Dialogs`): `message`, `detail`, 1 to 3 `buttons` in order, `default` (Return), `cancel` (Esc and the alert's own close; null is the last), `AlertStyle` Info/Warning/Critical, a `suppression` check box label, a `title`. A part the toolkit's alert has no place for is left out, never drawn another way. The answer is `AlertAnswered(window, id, button, suppressed)`: the button's index, the cancel index for Esc, -1 when the window closed first.

| Toolkit | Native | Esc when default = cancel | Style | Suppression | Title |
|---|---|---|---|---|---|
| AppKit | `NSAlert` sheet | no button (AppKit: one key equivalent a button) | yes | yes | no |
| Qt | `QMessageBox`, window-modal; on macOS Qt shows an `NSAlert` sheet | macOS: no button (Qt matches AppKit); elsewhere the escape button | icon | `QCheckBox` | window title |
| GTK | `GtkAlertDialog::choose()` | the cancel button | no | no | no |
| Raven | `Rdialog_open`, laid out as `Rmessage_box` | the cancel button (Raven's 0) | no | Raven check box | no |

# Base class

`ToolkitWindow` builds the native window through `bootstrapWindow()` in its constructor, then registers itself with the [session](/architecture/adapters.md) for the after-pump `settle()`.[^window]

| Situation | Path |
|---|---|
| the user closes the window (title bar, Cmd+W) | the adapter's native signal asks `allowsUserClose()`: while confirming (and not inside `close()`) it posts `WindowCloseRequested` and answers false, so the window stays; else the adapter runs `closed()` |
| code closes it | `close()` sets `$closing` and calls the adapter's `closeNative()`, whose native signal reaches `closed()`; a confirming window closes too |
| the native window is gone | `closed()`, the one close path, safe to call twice: `beforeClose()`, the content tree removed, the session stops tracking the window (dropping a resize not yet sent), `releaseNative()`, `WindowClosed` posted, the driver forgets the name |
| the content area changed size | `resized($w, $h)`: one `WindowResized` per pump, the last size |
| after every pump | `settle()`, empty unless the adapter compares something there; then `settleFocus()`: the window's focus is the deepest shown primitive whose `hasFocus()` says so, and a change posts `FocusLost` (old, unless removed) then `FocusGained` (new) |
| `alert()` | the adapter's `showAlert()` shows it without waiting; its answer calls `alertAnswered($button, $suppressed)`, which posts `AlertAnswered` once. Closing with it open: `closeAlert()` takes the toolkit's alert down while the window is whole, and `AlertAnswered` -1 is posted before `WindowClosed` |
| `present()` | the adapter shows the window, then `focusInitial()`: the `setInitialFocus()` primitive takes the focus through its own `focus()` |

# Adapter hooks

`bootstrapWindow()`, `isOpen()`, `closeNative()`, `releaseNative()`, `mountContent()` / `unmountContent()` (the content column's native into and out of the content area); `showAlert()`, `openUrl()`; optional `beforeClose()`, `closeAlert()` and `settle()`. Plus the contract's `present()`, `setTitle()`, `native()`, `size()`.

# Checked

On AppKit, Qt, GTK and Raven (2026-11-20): open, resize from code, confirm-close (the user's close posts `WindowCloseRequested` and the window stays), `close()` from code (`WindowClosed`, `isOpen()` false, the name reusable). Focus (2026-11-21, real-window harness, 42 checks per toolkit): initial focus and its `FocusGained`, `focus()` between two buttons posting `FocusLost` then `FocusGained`, no mail while focus stays, hiding the focused button's row, removing a focused primitive.

[^window]: ToolkitWindow
[^contract]: ToolkitPoweredWindow
