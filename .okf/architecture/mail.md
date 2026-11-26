---
type: Reference
title: Mail
description: The signals toolkit windows and primitives post, their names, and who posts each.
resource: src/Surface/Contracts/Toolkits/Mail/
tags: [topside, toolkits, mail, signals]
status: draft
generated: { by: claude-opus/5.5, at: 2026-11-25T15:20:00Z }
sources:
  - id: mail
    resource: src/Surface/Contracts/Toolkits/Mail/
    title: Toolkit mail contracts
  - id: signals
    resource: src/Surface/Core/Actions/Bootstrap/LoadSignals.php
    title: LoadSignals
---

# Delivery

A window or primitive posts through the session (`ToolkitWindow::post()`); the session hands it to the event loop, which dispatches it by `name()` through the app's signals. Before the loop is joined, mail waits in the session's outbox.[^mail]

# Window mail

`WindowEventOccurred` (carries `window`), implements `WindowMail`.

| Class | `name()` | Posted by |
|---|---|---|
| `WindowFocused` | `window.focused.{window}` | each adapter, once per activation |
| `WindowResized` | `window.resized.{window}` | `ToolkitWindow::resized()`, latest per pump; carries `width`, `height` |
| `WindowScaleChanged` | `window.scale-changed.{window}` | each adapter; carries `scale` |
| `WindowCloseRequested` | `window.close-requested.{window}` | `ToolkitWindow::allowsUserClose()` while confirming |
| `WindowClosed` | `window.closed.{window}` | `ToolkitWindow::closed()` |
| `WindowRightClicked` | `window.right-clicked.{window}` | nothing yet |
| `AlertAnswered` | `window.alert-answered.{window}` | `ToolkitWindow::alertAnswered()`, and `closed()` with -1; carries `id`, `button`, `suppressed` |

`LoadSignals` quits the app on `window.closed.{app.name}`, and hands every `PrimitiveMail` to the presentation driver, which runs the on-screen view's handler for it (see [Views](/architecture/views.md)), and every `AlertAnswered` to the driver's `answered()`, which runs the handler the view gave with that alert.[^signals]

# Menu mail

`MenuItemClicked` (`menu.clicked.{path}`), `MenuItemToggled` (`menu.toggled.{path}`), `MenuOptionChosen` (`menu.chosen.{path}`), the path being the menus' ids and the item's: posted by `MenuManager::deliver()` after the node's callback. `menu.activated` (`MenuNodeActivated`) is internal, from the toolkit's callback to the loop. See [Menu runtime](/architecture/menu-runtime.md).

# Primitive mail

`PrimitiveEventOccurred` (carries `window`, `path`, `uuid`), implements `PrimitiveMail`; copied unchanged from the earlier topside.

| Class | `name()` | Posted by |
|---|---|---|
| `ButtonClicked` | `view.clicked.{window}.{path}` | `TKButton::clicked()`, which each adapter calls from its native click; nothing once removed or while disabled |
| `WebViewLoaded` | `view.web.loaded.{window}.{path}` | `TKWebView::loaded($url)`, which an adapter calls when its engine finished a page; nothing once removed |
| `WebViewFailed` | `view.web.failed.{window}.{path}` | `TKWebView::failed($url, $reason)`, from the engine's failure, or at once on a load where `supported()` is false (reason `TKWebView::NO_ENGINE`); nothing once removed |
| `GroupClicked` | `view.group-clicked.{window}.{path}` | `TKPrimitiveGroup::groupClicked(x, y)`, which an adapter calls from its native click on a clickable group; nothing unless clickable, while disabled, or once removed |
| `FocusGained` | `view.focus-gained.{window}.{path}` | `ToolkitWindow::settleFocus()` after a pump, when the window's focus moved to this primitive (any move, `focus()` from code included) |
| `Toggled` | `view.toggled.{window}.{path}` | `toggled()` of `TKCheckbox`, `TKToggle` and `TKToggleButton`, which each adapter calls from its native signal; carries `on`. Nothing for a change made from code, once removed, or while disabled |
| `SelectionChanged` | `view.selection-changed.{window}.{path}` | `TKDropdown::chosen()`, `TKSegmentedControl::chosen()`, a `TKComboBox` choice (-1 for a completion); carries `index` and `option`. Nothing for a selection made from code, once removed, or while disabled |
| `TextChanged` | `view.text-changed.{window}.{path}` | `TKTextInput::typed()`, `TKComboBox::typed()`; carries `value`. Nothing for a value set from code |
| `TextSubmitted` | `view.text-submitted.{window}.{path}` | `submitted()` of both, on Return; carries `value` |
| `DateChanged` | `view.date-changed.{window}.{path}` | `TKDatepicker::picked()`; carries `date` |
| `ValueChanged` | `view.value-changed.{window}.{path}` | `TKSlider::slid()`, as the user moves it, the latest per pump; carries `value` |
| `SplitMoved` | `view.split-moved.{window}.{path}` | `TKSplitView::dragged()`, the latest per pump; carries `positions` (one) |
| `TabSelected` | `view.tab-selected.{window}.{path}` | `TKTabView::chosen()`; carries `page`, `index`, `title` |
| `ItemActivated`, `ItemActionClicked` | `view.item-activated...`, `view.item-action-clicked...` | `TKItemView`; carry `index` |
| `RowSelected` | `view.row-selected.{window}.{path}` | `TKTable::choseRow()`; carries `row`, `cells` |
| `NodeSelected`, `NodeExpanded`, `NodeActivated` | `view.node-selected...`, `view.node-expanded...`, `view.node-activated...` | `TKTree`; carry `id` (`expanded`) |
| `ViewScrolled` | `view.scrolled.{window}.{path}` | a watched text area or item view, the latest per pump; carries `position` |
| `FocusLost` | `view.focus-lost.{window}.{path}` | `ToolkitWindow::settleFocus()`, for the primitive the focus moved off; not for a removed one |

Also declared, posted by nothing yet (their primitives are not built): `RightClicked`, `ScrollChanged`, `ViewResized`.

[^mail]: Toolkit mail contracts
[^signals]: LoadSignals
