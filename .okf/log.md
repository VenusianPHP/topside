# Log

## 2026-11-22

* **Update**: [Menu runtime](/architecture/menu-runtime.md): Raven has a bar too, on Raven's own API: all four adapters have one. No topside code changed.
* **Update**: [Menu runtime](/architecture/menu-runtime.md): GTK has a bar too, on GTK's own API. No topside code changed.
* **Update**: [Menu runtime](/architecture/menu-runtime.md): Qt has a bar too, on Qt's own menu API. No topside code changed.
* **Update**: [Menu runtime](/architecture/menu-runtime.md): AppKit is the first adapter with a bar. No topside code changed.
* **Fix**: sample data in the menu code, tests and design names no real application: `My App`.
* **Fix**: the tests' session cleanup disconnected every other session: `disconnect()` unsets from the WeakMap the loop was walking, which skips the next entry. It takes a snapshot first. The random-order failure of the one-toolkit-at-a-time test is gone.
* **Creation**: [Menu runtime](/architecture/menu-runtime.md): stage 2a. TKMenuBar, the planner and mounter, the four steps, sharing, delivery, listening, `@menu`. No framework change: a bad menu is caught only where topside runs menu code. Fixed on the way: a handler that closes its own window no longer throws on the re-render. 553 tests on NTS and ZTS.
* **Update**: [Menu definitions](/architecture/menus.md): `Toggle::onToggle()`, `Choice::onChoose()`, and the keys that may stand alone, with `Shortcut::yieldsToFocus()`.
* **Update**: [Menu bars](/design/menus.md) amended a second time on Angel's go: bare keys with the focused widget first, a bad menu keeps the last good bar, `extend` changes a node by id, `onToggle()` and `onChoose()`.
* **Update**: [Menu definitions](/architecture/menus.md): stage 1 now matches the approved design. Mail by path, id or callback required where an item is built, a menu's id second, no platform filters, no `define()`, profiles under `toolkits.windows`. 427 tests on NTS and ZTS, and a real `config:cache` round trip.
* **Update**: [Menu bars](/design/menus.md) approved by Angel (text: commit `b82c974`), then amended in three places after his answers during stage 1: removing what is not there does nothing, only F1 to F12 are shortcuts alone, the Sharp example's Format menu has an id. The amendments and his words are in its status table.
* **Correction**: [Menu bars](/design/menus.md) rewritten as a draft awaiting approval. The first version was committed and built from without review, and had settled points the conversation had not. Each section is now marked agreed or proposed, the proposals are listed for approval, and the differences between the existing menu code and the design are listed under stage 1. The code is unchanged.
* **Creation**: [Menu definitions](/architecture/menus.md): stage 1 of the [menu bar design](/design/menus.md). The definition values, transforms, platform filters, shortcut checks, menu mail classes, `MenuManager` and `Menus`. [Config](/api/config.md): `toolkits.menus.default` and `.profiles` replace `toolkits.windows.*`; topside ships no menu. 341 tests (NTS and ZTS). An audit before the commit added `StandardItem::id()`, fixed a second application menu passing when one had an id, and marked the untested platform claims in the design.
* **Creation**: [Menu bars](/design/menus.md): the design the menu work follows. Two layers (definition DTOs, runtime), mail for config items and callbacks for code items, a bar resolved per window from window, route and view, no default profile. Probed: Raven's macOS application menu is an `NSMenu` that ext-appkit can edit, and the edit holds.
* **Update**: [Sharp](/architecture/sharp.md): `sharp:cache` moved to `Surface\Core\Console`, registered by Core's `ComputerServiceProvider`; the toolkits component alone has no command.
* **Correction**: `README.md` is the framework's README adapted to Topside, with the syntax highlighting section in it; the first one was a stub.
* **Update**: [Sharp](/architecture/sharp.md): `README.md` created with the syntax highlighting steps; the install takes `--force`.
* **Update**: [Primitives](/architecture/primitives.md): closing a tree node over the selection clears it. All sixteen primitives of the day have concretes in the four adapters.
* **Update**: [Primitives](/architecture/primitives.md), [Mail](/architecture/mail.md), [Views](/architecture/views.md): `TKTextArea`, `TKListView`, `TKCollectionView`, `TKTable`, `TKTree`, with plain-array elements and tags. 266 tests (NTS and ZTS).
* **Update**: [Primitives](/architecture/primitives.md), [Mail](/architecture/mail.md), [Views](/architecture/views.md): `TKScrollView`, `TKTabView`, `TKSplitView`, `TKGrid`, with elements and tags; `expands()`; drags post the latest per pump and hand the handler the value held at delivery.
* **Update**: [Primitives](/architecture/primitives.md), [Mail](/architecture/mail.md), [Views](/architecture/views.md): `TKSeparator`, `TKProgressBar`, `TKSpinner`, `TKSlider`, `TKToggleButton`, `TKSegmentedControl`, `TKImage`, with elements, tags and grammar; concretes in all four adapters, 25 real-window checks each. [Sharp](/architecture/sharp.md): PhpStorm's TextMate PHP colours do not match its PHP highlighter's.

## 2026-11-21

* **Correction**: [Sharp](/architecture/sharp.md): PhpStorm's step is the Text file type; the TextMate one written before is never listed in its settings.
* **Update**: [Sharp](/architecture/sharp.md): a TextMate grammar for Cursor and PhpStorm in `editors/sharp/`.
* **Update**: [Primitives](/architecture/primitives.md), [Mail](/architecture/mail.md), [Views](/architecture/views.md): `TKTextInput`, `TKComboBox`, `TKDatepicker`, `TKLink`, their elements and Sharp tags; `focusMovedAway()`. 221 tests (NTS and ZTS); concretes in all four adapters, 30 real-window checks each.
* **Creation**: [Sharp](/architecture/sharp.md): the template lexer, compiler, tag resolver, cache and `sharp:cache`; `View::render()` takes a template's name.
* **Update**: [Views](/architecture/views.md): the demo `Card` takes a `width` (its minimum); both demo cards are 340 and no longer resize with the summary line.
* **Update**: [Primitives](/architecture/primitives.md), [Mail](/architecture/mail.md), [Views](/architecture/views.md): `TKCheckbox`, `TKToggle`, `TKDropdown` with their factories, mail (`Toggled`, `SelectionChanged`) and elements; the demo has a settings card. Concretes in all four adapters; each passes an 11-check real-window harness.
* **Update**: [Views](/architecture/views.md): components inside components (`Component::make()`, `ComponentElement`, `Children::slot()`), the demo `Card`; a failed render leaves nothing behind. 34 checks; the card checked on all four toolkits.
* **Creation**: [Views](/architecture/views.md): `Component`, elements (column, row, label, button, spacer), the `Mounter`, `MountedView`; `View::render()` puts a component on screen and its handlers' changes re-render it. `DemoHomeView` is a component. [Mail](/architecture/mail.md): `LoadSignals` delivers primitive mail to the view; `Navigator::clicked()` removed. Checked on the fake toolkit (22 checks) and in real windows on all four toolkits.

* **Update**: [Mail](/architecture/mail.md) and [Window lifecycle](/architecture/window-lifecycle.md): focus tracking (`settleFocus()` after every pump, `FocusGained` / `FocusLost` for every move), `setInitialFocus()`, `focused()`; the rest of the primitive mail is declared. Commit `e1f2c11`.
* **Update**: [Primitives](/architecture/primitives.md): the primitive map replaces `concreteFor()`; fill, alignment and minimum size are stored; `childLayoutChanged()` and `childVisibilityChanged()` hooks; context menus refuse; a disabled button posts no click. All four toolkits have concrete primitives, each passing the 35-check real-window harness. Commits `eeeb94a`, `9933f62`, `6dcf56b`.
* **Update**: [Toolkit adapters](/architecture/adapters.md) (`$primitives`, `primitiveFor()`), [Window lifecycle](/architecture/window-lifecycle.md) (`column()`, `content()`, mount hooks).

* **Initialization**: bundle created with the package's first commit (`1a004fd`): [Toolkit adapters](/architecture/adapters.md), [Window lifecycle](/architecture/window-lifecycle.md), [Primitives](/architecture/primitives.md), [Mail](/architecture/mail.md), [Config](/api/config.md).
* Checked: the window lifecycle on AppKit, Qt, GTK and Raven (2026-11-20); the primitives against a fake toolkit, 57 checks; the AppKit enabled cascade on real widgets, which lives in `jovian/venusian-appkit`.
