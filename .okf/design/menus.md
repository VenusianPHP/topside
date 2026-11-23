---
type: Design
title: Menu bars
description: The design for menu bars, approved 2026-11-22 - definition DTOs written in config, a controller, a component or a Sharp template, a runtime layer that shows them, how a window's bar is chosen, standard items, mail, and the build stages.
tags: [topside, toolkits, menus, design]
status: draft
generated: { by: claude-fable/5.2, at: 2026-11-22T16:57:08Z }
sources:
  - id: element
    resource: src/Surface/Toolkits/Views/Elements/Element.php
    title: Element (apply)
  - id: mounter
    resource: src/Surface/Toolkits/Views/Mounter.php
    title: Mounter (reconcile)
  - id: driver
    resource: src/Surface/Toolkits/Drivers/PresentationTreeDriver.php
    title: PresentationTreeDriver
  - id: navigator
    resource: src/Surface/Toolkits/Navigation/Navigator.php
    title: Navigator
  - id: signals
    resource: src/Surface/Core/Actions/Bootstrap/LoadSignals.php
    title: LoadSignals
  - id: mail
    resource: /architecture/mail.md
    title: Mail (names in use today)
  - id: sharp
    resource: /architecture/sharp.md
    title: Sharp
  - id: config-cache
    resource: ../framework/src/Voyager/Core/Console/ConfigCacheCommand.php
    title: ConfigCacheCommand (var_export)
  - id: dispatcher
    resource: ../framework/src/Voyager/Signals/SignalDispatcher.php
    title: SignalDispatcher (listen, wildcards)
  - id: rejected
    resource: ../../rejected/topside/src/Surface/Toolkits/Menus/
    title: The rejected menu model
  - id: old-bars
    resource: ../../jovian/venusian-appkit/.okf/architecture/windows-and-menus.md
    title: The retired AppKit bar, and its Qt, GTK and Raven siblings under each adapter's deprecated2/
  - id: raven-stub
    resource: ../../php-io-extensions/raven/stubs/raven_controls.stub.php
    title: Rmenu_bar_set_native
  - id: raven-probe
    resource: probe run 2026-11-22, PHP 8.4 NTS, ext-raven and ext-appkit in one process
    title: Raven application menu probe
  - id: stage1-code
    resource: /architecture/menus.md
    title: The menu code committed before this design was approved
---

# Status: approved

Angel approved this document on 2026-11-22, the proposals included; the text as he approved it is commit `b82c974`. Amended twice that day, each time after his answer, and nowhere else. After three questions asked during stage 1:

| Where | Amendment | His answer |
|---|---|---|
| Changing a bar | `without()` ignores what is not there | "So you think throwing and crashing the application is the right thing to do?" |
| Shortcuts | only F1 to F12 work with no modifier (withdrawn by the second amendment) | "It seems like you're trying to make me pick and choose instead of following best practices..." |
| In a Sharp template | the example's Format menu has `id="format"` | "You can add id to the menu, if an only if, you actually researched to m ake sure your proposal does break things" |

After he saw stage 1 and said "alright then lfg baby!" to these corrections:

| Where | Amendment | What he had said |
|---|---|---|
| Shortcuts, adapters, stage 2b | keys that type nothing may stand alone, and the focused widget gets such a key first | "catering to gtk doesn't seem like the right move when qt and appkit do other things. You also didnt mention how raven tends to handle these things." |
| A bad menu | it is reported, the window keeps its last good bar, and the app keeps running | "yes I told you how I felt about this, so...okay?" |
| In a Sharp template, the DTOs | inside `extend` an id that is there is changed, not added again; `Toggle` and `Choice` can be given a callback as `Item` can | "So how SHOULD this be handled?" |

Later that day the sample application's name in the examples became `My App`, on Angel's rule that topside, the framework and the skeleton name no application of his.

**How it is used once approved**

* The stages below are built in their order, as written.
* The document does not change during the build. If the build shows that something here is wrong, missing or cannot be done, the work stops and the question goes to Angel. The document changes only with his answer.
* A fact that still needs measuring is measured in the stage that names it. If the measurement disagrees with this document, that is a stop too.
* Approving this document approves stages 1 to 3. Stage 4 has its own design, written and approved before it starts.

**How to read it**

Every section is marked one of two ways:

* **Agreed:** decided in conversation on 2026-11-22. What Angel said is quoted or summarised.
* **Proposed:** a detail the conversation did not settle, with a recommendation and its reason. Each is listed again in [Proposals to approve](#proposals-to-approve), so they can be accepted or changed in one pass.

**Code that already exists**

Menu code was committed before any design was approved (commit `5aac273`).[^stage1-code] It has no standing. Where it differs from the approved design it changes. [Stage 1](#1-definitions) lists every difference.

# What the author gets

**Agreed.** From the first request and the three decisions:

* Menus are defined in config as named profiles, when a window is opened, in a controller, in a component, in a Sharp template, or in several of these together.
* The focused window's bar is the one shown. In a single window, going to another page can change the bar.
* Nothing is forced. There is no About or Quit the author did not write. About is the OS's own panel with the author's fields, or the author's own window, or absent.
* Cut, copy, paste, undo, redo and select-all work in text fields without the author knowing how a toolkit routes them.
* A shortcut is written once and is right on each platform.
* The author never meets a toolkit, a session, a pump or the loop.

What the rejected model did, and this one must not:[^rejected]

| Rejected | Here |
|---|---|
| Nested arrays with `'role' => 'about'` strings, checked by a parser with some twenty exceptions | Typed DTOs |
| The framework owned About's content and always showed its own dialog | The author picks the panel, their own window, or nothing |
| `MenuActivated(id)` for every item, the id made from the label path | A callback beside the item, or mail under ids the author chose |
| `setItemEnabled('file.save', false)`, `setToggle()`, `setRadio()` on the window | State is part of the definition; a new definition is compared with the last |

# Two layers

**Agreed.** "I am down for 2 tiers, if by that you mean a definition layer (config, controller|component|sharp) and a runtime layer."

| | Definition layer | Runtime layer |
|---|---|---|
| What | Readonly DTOs that describe menus | The bar shown on a window: native items, live state, which handler each item runs |
| Who makes it | The author | The framework, from a definition |
| Changes by | Never. Every change is a copy | Comparing a new definition with the one it was built from |

There is no third layer between them. The DTOs the author writes are the ones the runtime reads. Views already work this way: an `Element` describes, and the `Mounter` builds the primitive and afterwards sets only what differs.[^element][^mounter]

# Definition layer

## The DTOs

**Agreed:** DTOs and not arrays; built statically, so they can sit in config and be returned by methods that put menus together; required things are arguments, so an incomplete menu cannot be built; every change returns a copy.

**Proposed:** the class list and argument names below.

All in `Surface\Toolkits\Menus\`. Every class is `final readonly` and has a static `make()`. Every class implements `__set_state()`, because `config:cache` writes config with `var_export()`.[^config-cache]

| Class | Is | `make()` takes | Optional, each returns a copy |
|---|---|---|---|
| `MenuBar` | A whole bar | `Menu ...$menus` | see [Changing a bar](#changing-a-bar) |
| `Menu` | A menu of the bar, or a submenu | `string $title`, then optionally its id as a second string, then its nodes | `titled()`, `enabled()`, and the node methods in [Changing a bar](#changing-a-bar) |
| `Item` | Something to choose | `string $label`, and `id:` or `onClick:` or both | `shortcut()`, `enabled()`, `label()`, `onClick()` |
| `Toggle` | An item with a check mark | `string $label`, `bool $on`, and `id:` or `onToggle:` or both | `shortcut()`, `enabled()`, `label()`, `on()`, `onToggle()` |
| `Choice` | A radio group: several items, one chosen | `array $options` (value => label), `string $chosen`, and `id:` or `onChoose:` or both | `enabled()`, `chosen()`, `onChoose()` |
| `Separator` | A line | nothing | |
| `StandardItem` | An item the platform knows | made by `Standard::*()` and `About::*()` | `label()`, `shortcut()`, `enabled()`, `id()`, `onClick()` |
| `Shortcut` | A key and its modifiers | see [Shortcuts](#shortcuts) | `shift()`, `alt()`, `ctrl()` |

A node of a `Menu` is an `Item`, `Toggle`, `Choice`, `Separator`, `StandardItem` or another `Menu`.

```php
Menu::make('File', 'file',                  // title, then the id
    Item::make('Save', id: 'save')->shortcut(Shortcut::primary('S')),
    Separator::make(),
    Standard::closeWindow(),
)
```

The menu's id is its second argument and not `id:`, because PHP does not allow a named argument before the list of nodes. The sample shown in conversation, with `id: 'file'` ahead of the items, was not valid PHP.

`Item::make('Save')` alone is an error where it is written: an item that does nothing cannot be built.

## What an item does

**Agreed.** "If a menu is defined with a DTO within a config, you wouldn't be able to have callbacks and hooks, wouldn't it just have some mail that could be fired? ... Then in a controller, component or sharp template you could define menu items with callbacks when clicked."

| Where the item is defined | What it does when chosen |
|---|---|
| Config | Posts mail. It has an `id`. This is the only form config can hold: a closure cannot be cached.[^config-cache] |
| A controller, a component, a Sharp template | Runs a callback. It may have an `id` as well, and then the callback runs first and the mail is posted after. |

A profile in config that holds a callback is refused when it is first read, naming the profile and the item.

## Mail

**Agreed:** config items post mail; the author listens in a controller, a component or a Sharp template. Angel's form for the name: `<menu_name>.<menu_item>.clicked`.

**Proposed (name):** the name is built from ids the author wrote, never from labels. If names came from labels, renaming "Save" to "Save Document", or translating the app, would silently break every listener. This was put to Angel and not yet answered.

**Proposed (order):** `menu.clicked.<menu id>.<item id>`, which puts the event second. Every name topside posts today has that order: `window.closed.{window}`, `view.clicked.{window}.{path}`.[^mail] It is Angel's form with the last part moved. If he prefers `menu.<menu id>.<item id>.clicked`, only the name changes.

| Class | `name()` | Carries | Posted when |
|---|---|---|---|
| `MenuItemClicked` | `menu.clicked.{path}` | `window`, `path` | an `Item`, or a `StandardItem` given an id, is chosen |
| `MenuItemToggled` | `menu.toggled.{path}` | `window`, `path`, `on` | the user flips a `Toggle` |
| `MenuOptionChosen` | `menu.chosen.{path}` | `window`, `path`, `option` | the user changes a `Choice` |

Rules for the path:

* It is the id of each menu from the bar down to the item, then the item's id, joined by dots. `Item::make('Save', id: 'save')` in `Menu::make('File', 'file', ...)` posts `menu.clicked.file.save`. In a submenu with id `export` it posts `menu.clicked.file.export.pdf`.
* An id is lower case letters, digits and `-`.
* A menu that holds an item with an id must have an id itself. The error says which menu and which item.
* Two nodes of one menu cannot share an id.
* `window` is the window that was focused, or null for the bar macOS shows when no window is.
* Nothing is posted for a change made from code, or for a disabled item.

**Proposed (listening):**

| Where | How | Lives until |
|---|---|---|
| Anywhere | `$app['signals']->listen('menu.clicked.file.save', ...)`, as for any mail. Wildcards work: `menu.clicked.file.*`.[^dispatcher] | removed by the author |
| A component, or a Sharp `@script` | A method marked `#[OnMenu('file.save')]`. It runs while the view is on screen, and the view renders again after it, as after a button's handler.[^driver] It may take the mail as its argument. | the view leaves the screen |
| A controller | `Menus::on('file.save', fn (MenuMail $mail) => ...)` inside the action | that window opens another route |

## Changing a bar

**Agreed.** "A premade profile that contains for example `App | File | Edit` could be formed and then modified from that point then handed off for rendering. So adding to and replacing would be what I think I would want."

A `MenuBar` is a value. Every method returns a copy.

| Method | Gives |
|---|---|
| `with(Menu ...$menus)` | the bar with these menus at the end; a menu whose key is already there replaces that one |
| `without(string ...$keys)` | the bar without those menus |
| `extend(string $key, Closure(Menu): Menu)` | the bar with that one menu changed |
| `item(string $path, Closure)` | the bar with that one item changed, found by the same path its mail uses |
| `before(string $key, Menu $menu)`, `after(...)` | the bar with a menu placed beside another |
| `menu(string $key)`, `find(string $path)` | reading |

A menu's key is its id, or its title when it has none. `Menu` has the same methods for its nodes: `append()`, `prepend()`, `before()`, `after()`, `without()`, `replace()`. Removing a menu or item that is not there does nothing: it is already gone. Changing one, or placing beside one, that is not there is an error, not a silent no-op.

```php
Menus::profile('app')
    ->extend('file', fn (Menu $file) => $file->append(Item::make('Export PDF', onClick: $this->export(...))))
    ->with(Menu::make('Format', ...))
    ->without('help');
```

## Standard items

**Agreed:** standard items are typed values the author places; none is forced. They follow the rule of every item: "when you give the macro the same rules, either mail or callbacks depending where they are defined". By default the edit items act on the focused text widget and post nothing; Quit posts the `quit.requested` mail that exists today.[^signals]

**Proposed:** the list below.

| Factory | Does, when the author overrides nothing |
|---|---|
| `Standard::quit()` | posts `quit.requested` |
| `Standard::preferences(id:)` or `(onClick:)` | what the author gave; one of the two is required |
| `Standard::hide()`, `hideOthers()`, `showAll()` | the OS's own. macOS only; left out of the bar elsewhere |
| `Standard::minimize()`, `closeWindow()`, `fullscreen()` | acts on the focused window |
| `Standard::undo()`, `redo()`, `cut()`, `copy()`, `paste()`, `selectAll()` | acts on the focused text widget |
| `Standard::editMenu()` | a ready `Menu` (id `edit`): undo, redo, a line, cut, copy, paste, select all |
| `Standard::windowMenu()` | a ready `Menu` (id `window`): minimize, fullscreen, close window |

* Each kind has the platform's usual label and shortcut. `->label()` and `->shortcut()` replace them.
* `->id('copy')` makes a standard item post mail instead of doing what it does by itself; `->onClick(...)` makes it run a callback. Config can hold the first.
* A toolkit that cannot do a kind shows the item disabled and logs it once, naming the kind and the toolkit. It is never dropped silently. Read from the extension stubs, not yet measured: fullscreen is not bound on Qt, GTK or Raven, and minimize is not bound on Qt or GTK.

**Agreed (edit keys):** the platform's undo, redo, cut, copy, paste and select-all keys reach a focused text field whether or not the bar holds those items. The Edit menu shows them; it is not what makes them work.

## About

**Agreed.** Three choices, and the author makes one: the OS panel filled in with their fields, their own window, or no About at all.

| Factory | Does |
|---|---|
| `About::panel(name:, version:, copyright:, credits:)` | shows the toolkit's own About panel with these fields. Only `name` is required. |
| `About::opens(AboutView::class, title:, width:, height:)` | opens a window showing that view |
| `About::item(id:)` or `(onClick:)` | posts mail or runs a callback; the author shows whatever they like, for example a page in the same window |
| nothing | there is no About |

**Proposed:**

* `About::opens()` is built in stage 4. It needs a second window that can show a view, and today only the first window can.[^driver] It was named in conversation; `About::item()` was not, and is added so an own About is possible from stage 1.
* AppKit and GTK have a native About panel. Qt and Raven have none, so there the adapter builds a small dialog from the same fields.

## The application menu

**What Angel was told:** "each adapter decides placement" of standard items (macOS application menu, Qt roles, Help on GTK).

**Proposed instead:** the tree is literal. An item appears in the menu the author put it in, on every platform, and the framework moves nothing. The author marks which menu is the application menu:

```php
MenuBar::make(
    Menu::application(
        About::panel(name: 'My App', version: '11.0'),
        Separator::make(),
        Standard::quit(),
    ),
    Menu::make('File', 'file', ...),
)
```

* **On macOS**, on every toolkit, `Menu::application()` is the first menu, the one the OS titles with the app's name.
* **Elsewhere** it is an ordinary first menu, titled with `config('app.name')`. Its id is `app`.
* A bar has at most one. On macOS a bar with none gets an empty one, because macOS always treats the first menu as the application menu and would otherwise show the author's File menu under the app's name.

Reason for the change: moving items by kind means that a menu appears on macOS which the author never wrote, holding items they placed elsewhere. That is the framework deciding the author's menus again. It also brings back position rules of the "first folder" kind.

## Shortcuts

**Agreed:** typed, written once, right on each platform.

**Proposed:** the details.

```php
Shortcut::primary('S')              // Cmd+S on macOS, Ctrl+S elsewhere
Shortcut::primary('S')->shift()
Shortcut::key(ShortcutKey::F5)
Shortcut::of('Primary+Shift+S')     // the same from text, for a Sharp attribute
```

* `ShortcutKey` is an enum: letters, digits, punctuation, F1 to F12, arrows, Home, End, Page Up, Page Down, Space, Enter, Tab, Escape, Backspace, Delete.
* Delete, Backspace, the arrows, Home, End, Page Up, Page Down and F1 to F12 may stand alone, or with Shift only. Every other key needs Primary, Ctrl or Alt: the keys that type, and Tab, Enter and Escape, which belong to focus and dialogs.
* A shortcut with no Primary, Ctrl or Alt goes to the focused widget first, and the menu gets it only when that widget does not use it. So a text field keeps its editing keys on every toolkit.
* Two items of one bar with the same shortcut are an error when the bar is about to be shown, naming both.

## Profiles in config

**Agreed:** named profiles in configuration, and methods that put menus together.

**Proposed:** the keys stay the ones `config/toolkits.php` had before this work, `windows.default_menu` and `windows.menus`. Nobody asked for them to be renamed.

```php
'windows' => [
    'default_menu' => 'app',          // the profile a window shows when nothing gives it another; null for none
    'menus' => [
        'app'    => MenuBar::make(Menu::application(...), Standard::editMenu()),
        'editor' => EditorMenus::bar(),   // a static method that returns a MenuBar
    ],
],
```

* A profile is a `MenuBar`. A method call in the config file is ordinary PHP and is cached as the value it returned.
* Topside itself ships `'default_menu' => null` and `'menus' => []`, with the example above as a comment. The About and Quit profile it shipped is removed: a menu the package ships is a menu the author did not write.
* `Menus::profile('editor')` reads one.

# Where a window's bar comes from

**Agreed:**

* "Any menu defined at render time, for example adding a menu item or redefining the whole menu inside a component or sharp template, takes priority."
* A second window "could define its own, or get handed the one from the window that opened the second one, dev's choice". "When that second window becomes the focused one, whatever its menu bar definitions are is what gets rendered."

**Proposed:** the order and the calls.

Each open window has one bar. It is worked out in four steps, and each step receives the bar from the step before:

| Step | Where the author writes it | Forms |
|---|---|---|
| 1. The window | `ToolkitWindows::open('main', 1024, 678, menu: ...)` | a profile name; a `MenuBar`; `Menus::sharedWith('main')`; or nothing, which is the `default_menu` profile, or no bar when that is null |
| 2. The route | `Route::get('/editor', [...])->menu(...)` | a profile name or `MenuBar` to replace the bar; a closure `fn (MenuBar $bar): MenuBar` to change it |
| 3. The controller | `Menus::page(...)` inside the action | the same three forms |
| 4. The view | `menu(MenuBar $bar): MenuBar` on a component; `@menu` in a Sharp template | returns the bar to show |

The last step that says anything wins. Adding to a profile, replacing one menu and replacing the whole bar are one mechanism: return the bar changed, or return a different bar.

```php
// a component: state is read when it renders, so there is nothing to set later
public function menu(MenuBar $bar): MenuBar
{
    return $bar
        ->item('file.save', fn (Item $save) => $save->enabled($this->dirty))
        ->with(Menu::make('Format',
            Toggle::make('Word Wrap', on: $this->wrap, onToggle: fn (bool $on) => $this->wrap = $on),
        ));
}
```

* `Menus::sharedWith('main')` is the opener's bar, followed live: the second window shows whatever `main` shows now, including later changes, and its items run `main`'s handlers. A copy taken once would go stale the first time `main` changed page.
* `Menus::window('main', $bar)` replaces step 1 for a window that is already open.

## In a Sharp template

**Agreed:** a Sharp template is a place to define menus, equal to a controller and a component.

**Proposed:** a third block beside `@script` and `@template`.[^sharp]

```
@menu
<Menu key="file" extend>
    <Item id="save" @click="save" :enabled="$dirty"/>
</Menu>
<Menu title="Format" id="format">
    <Toggle label="Word Wrap" :on="$wrap" @toggle="fn ($on) => $this->wrap = $on"/>
    <Separator/>
    <Item label="Export PDF" id="export"/>
</Menu>
@endmenu
```

* The block is laid over the bar from steps 1 to 3. A `<Menu>` whose key is new is added at the end. One whose key is already there replaces that menu, or with `extend` changes it. Inside `extend`, a node whose id is already there is changed: only the attributes written change, and it keeps the rest and its place, as `item()` does. The other nodes are added at the end.
* `@menu replace` makes the block the whole bar.
* Tags: `<Menu>`, `<Item>`, `<Toggle>`, `<Choice>`, `<Separator>`, `<Standard kind="copy">`, `<EditMenu>`, `<WindowMenu>`, `<AboutPanel>`, `<AboutItem>`. Their attributes are read off the DTOs, as element tags are read off elements. `@click`, `@toggle` and `@choose` are the callbacks.
* For anything finer (removing a menu, changing an inherited item that has no id, placing a menu before another) the template writes `menu()` in `@script` instead. A template has one or the other, not both.

## When it is worked out again

**Proposed.**

| When | What happens |
|---|---|
| The window opens | worked out and built before the window is first shown |
| The window shows another route | steps 2 to 4 run for the new route and view |
| A handler of the window's view ran | step 4 runs again with `render()`; only what differs is touched |
| `Menus::window()` or `Menus::page()` | that window, and every window sharing its bar |
| The window closes | its bar is released |

Gaining focus works nothing out. Every window's bar is already current, so focus only shows it: on macOS the window's bar becomes the system bar, and where bars sit inside the window nothing needs doing. The adapter does this inside its own focus callback and not from mail. Mail arrives one turn of the loop later, and on AppKit a shortcut is matched against whichever bar is installed at that instant.[^old-bars]

On macOS, when no window of the app is focused, the bar shown is the `default_menu` profile.

## Toggle and choice state

**Proposed.** A definition says what a toggle or choice starts as. When the user clicks, the check mark changes at once and the new value goes to the callback or the mail. A later definition changes the mark only when its value differs from the definition before it, which is the rule `Element::apply()` follows.[^element] So a view that keeps the state (`on: $this->wrap`) stays in step, and a toggle from config that nothing controls keeps what the user chose.

## Callbacks

**Proposed.** A callback runs in a turn of the loop, as a button's handler does, never inside a toolkit's own callback. After it runs, the view on screen in the window that owns the bar renders again.

## A bad menu

**Agreed.** A menu that cannot be built, changed or shown while the app runs does not end it. The failure is reported through the exception handler, naming the window and the error, and that window keeps its last good bar. The definitions still throw, so the mistake is visible in tests and in the report.

## Modal child windows

**Proposed.** A modal child window has no bar of its own. While it is focused, its opener's bar stays visible with every item disabled except the edit items, as the retired bars did.[^old-bars] Child windows are not ported yet, so nothing is built for this in stages 1 to 3; the rule is recorded for when they are.

# Platforms

**Agreed.** "If Raven can support AppKit rendered menu bars on macOS we would do that when on macOS. If not then have the internal logic for in-window rendered menu bars."

| | macOS | Linux |
|---|---|---|
| AppKit | the system bar | not applicable |
| Qt | the system bar | inside each window |
| GTK | the system bar | inside each window |
| Raven | the system bar (Raven's native mode, on by default)[^raven-stub] | inside each window |

The rules above are the same on every row. Only where the bar is drawn differs.

## Runtime contract

**Proposed.** `Surface\Contracts\Toolkits\Menus\TKMenuBar` is what each adapter implements, one per window and one for the bar shown when no window is focused.

| Method | Does |
|---|---|
| `insert($parent, $index, $node)` | makes the native item under a parent, or under the bar |
| `remove($uuid)` | removes it and everything under it |
| `move($uuid, $parent, $index)` | |
| `change($uuid, $changes)` | label, enabled, on, chosen, shortcut: only what changed |
| `show()` | makes this the visible bar where one bar is shared |
| `release()` | drops every native item |
| `supports(StandardKind)` | whether the toolkit can do a kind |

Adapters receive single operations and never a whole bar to rebuild. A rebuild after every handler would close a menu the user has open.

In topside: `MenuMounter` compares a new definition with the one shown and calls the `TKMenuBar`; `MenuManager` holds the profiles, each window's bar and the four steps; `Menus` is the static door beside `ToolkitWindows` and `View`.

## What each adapter does

Each retired bar under `jovian/venusian-*/deprecated2/Windows/` already solves its toolkit's item, shortcut and edit wiring, and is the starting point.[^old-bars]

| | AppKit | Qt | GTK | Raven |
|---|---|---|---|---|
| Bar | one `NSMenu` per window; `show()` is `setMainMenu` | `QMenuBar` per window; a parentless one on macOS for no window focused | `GMenu` model and `GtkPopoverMenuBar` | `R_WIDGET_MENU_BAR` per window, native mode |
| Application menu on macOS | the first `NSMenu` | Qt menu roles; every other item `NO_ROLE` so Qt guesses nothing from labels | the first menu | Raven builds its own; the adapter edits it through ext-appkit |
| A shortcut with no Primary, Ctrl or Alt | handed to the focused text before the menu | Qt's own order; the retired filter that overrode it is not ported for these | registered after the widget, not in the capture phase | in a window Raven's own order; in the system bar only the key's label, and Raven dispatches it |
| Edit items | the edit selectors, sent to the first responder | the focused text widget's slot | the widget's clipboard actions | the key sent into the window |
| About panel | the standard About panel with options | built dialog | `GtkAboutDialog` | built dialog |

## Measured and not measured

| Fact | State |
|---|---|
| Raven's macOS application menu is an ordinary `NSMenu`: About, Hide, Hide Others, Show All, Quit | measured[^raven-probe] |
| A Raven window's bar appears in the system bar after it | measured[^raven-probe] |
| Removing Raven's About item through ext-appkit holds | measured[^raven-probe] |
| Inserting, relabelling or reordering in Raven's application menu holds; Quit still works after an edit | not measured; stage 3 |
| The system bar swaps when a second Raven window takes focus | not measured (the probe never became the active app); stage 3 |
| The application menu's title follows `app.name` | not measured; the probe read `php`, likely the unbundled process's name; stage 3 |
| On AppKit, Cmd+C and its siblings reach a text field only through menu items | recorded from the retired bar[^old-bars] |
| A detached, never-shown `NSMenu` holding the edit items, asked from the session's key filter, makes them work with no Edit menu | an idea from reading the bindings, not measured; stage 3 |
| GTK on macOS takes Cmd+A, C, V, X in the widget with no Edit menu | measured 2026-10-10; undo and redo not measured |
| A focused text field keeps a bare Delete from a menu: Raven in a window, Qt | measured 2026-11-22, headless and offscreen |
| AppKit gives a bare Delete or Backspace to the menu, and the focused editor edits when handed it first | measured 2026-11-22 on a hidden window, each half on its own; end to end in stage 2b |
| GTK and Raven's system bar give such a key to the focused widget when wired as above | read from their sources, not measured; stage 2b |
| Qt and Raven take all six in the widget with no Edit menu | read from the retired adapters, not measured; stage 3 |

If any of the unmeasured rows turns out false, the work stops and the question goes to Angel.

# Stages

**Agreed.** This is the order given in conversation. Each stage ends with a report to Angel of what was built and what its checks showed.

## 1. Definitions

The DTOs, the profile registry, the changes to a bar, the mail classes and names, the config keys. Tests only; no window and no toolkit.

Code exists for this stage.[^stage1-code] To match this document it changes as follows:

| Existing code | Becomes |
|---|---|
| Mail `menu.clicked.{id}`, one free-form id per item, dots allowed | the path of menu ids and the item id; ids without dots; a menu holding an item with an id needs an id |
| `Item::make($label, ?$id)`; an item that does nothing is refused only when added to a menu | `id:` or the callback is required by `make()` |
| `Standard::preferences(?$id)`, `About::item(?$id)` with nothing required | one of `id:` or `onClick:` required |
| `->onMac()` and `->offMac()` on every node | removed (see proposal 11) |
| Config keys `toolkits.menus.default` and `.profiles` | `toolkits.windows.default_menu` and `.menus` |
| A profile may be `[Class, 'method']` or, through `Menus::define()`, a closure | removed; a profile is a `MenuBar` in config |
| `/architecture/menus.md` describes the code above as built | rewritten to describe what this stage leaves |

The second amendment then adds `onToggle()` and `onChoose()` and the keys that may stand alone. Kept as it is otherwise: `MenuBar` and `Menu` and their changing methods, `Toggle`, `Choice`, `Separator`, `Shortcut` and `ShortcutKey`, `StandardItem` and its `id()` and `onClick()`, `Menu::application()`, `__set_state()` on every class.

Checks: every error in this document, by its message; every changing method returns a copy and leaves the original alone; a bar written by `var_export()` and read back equals the original; a config profile holding a callback is refused; shortcut parsing and what Primary means on each platform.

## 2. Runtime, then the four bars

**2a, in topside, against the fake toolkit:** `TKMenuBar`, `MenuMounter`, `MenuManager`'s four steps, `ToolkitWindows::open(..., menu:)`, `Menus::window()`, `Menus::page()`, `Menus::sharedWith()`, `Menus::on()`, `Route::get()` returning the route so `->menu()` chains, `menu()` on `Component`, `#[OnMenu]`, the `@menu` block and its tags, delivery of callbacks and mail.

Checks: the list of operations for each kind of change, and none for an unchanged bar; the order of the four steps; a bad menu is reported and the last good bar stays; a callback renders its view again; mail is posted with the focused window; a shared bar changes with the bar it shares; a closed window releases its bar; the `@menu` block gives the same bar as the same menus written in `menu()`.

**2b, the adapters, in this order:** AppKit, Qt, GTK, Raven. Raven's bar is built here without the editing of its application menu, which waits for stage 3.

Checks: a real-window run per toolkit, as the primitives have: items, submenus, lines, toggle, choice, enabled, relabel, a shortcut fires, a bare Delete goes to a focused text input and otherwise to the menu, edit items act on a focused text input, the About panel opens, the bar follows focus between two windows, and on macOS the default bar shows with no window focused.

## 3. The two probes, and what waits on them

* **AppKit edit keys with no Edit menu.** Measure the detached-menu idea; then build the guarantee. Measure the six keys on Qt, GTK and Raven with no Edit menu.
* **Raven's application menu.** Measure inserting, relabelling and reordering, Quit after an edit, the swap between two focused windows, and the menu's title. Then build the editing so the menu matches `Menu::application()`.

The window-state bindings missing from ext-qt and ext-gtk (minimize, fullscreen) are a change to the extensions. Before touching them the work stops and asks.

## 4. A view and navigation for every window

Today one `Navigator` and one `PresentationTreeDriver` serve the whole app, and only the first window shows a view.[^navigator][^driver] Until this stage a second window has a bar from step 1 only. This stage gives every window its own, so steps 2 to 4 apply to each, and `About::opens()` is built on it. It changes navigation, not menus, and its design is written and approved before it starts.

## Not in this work

Context menus from the same DTOs; menu items bound to commands; a console command that prints a bar as each platform shows it; items contributed by components nested inside a view. Each would be its own design.

# Proposals to approve

Everything marked **Proposed** above, in one list. For each: approve it, or say what it should be.

| # | Proposal | Short reason |
|---|---|---|
| 1 | Mail names come from ids the author writes, never from labels | a relabel or a translation would break listeners |
| 2 | The name is `menu.clicked.<menu id>.<item id>` (also `menu.toggled.`, `menu.chosen.`) | the order every other mail in topside uses; your form was `<menu>.<item>.clicked` |
| 3 | Listening: `#[OnMenu('file.save')]` in a component or Sharp script; `Menus::on()` in a controller, dropped when the window changes route | you said these are where you listen; this is how |
| 4 | The class list: `MenuBar`, `Menu`, `Item`, `Toggle`, `Choice`, `Separator`, `StandardItem`, `Shortcut`; a menu's id is its second argument | PHP allows no named argument before the nodes |
| 5 | The standard items listed, with `editMenu()` and `windowMenu()` ready-made | |
| 6 | A kind a toolkit cannot do is shown disabled and logged once | never silently missing |
| 7 | `About::item()` added now; `About::opens(View::class)` built in stage 4 | `opens` needs a second window that can show a view |
| 8 | The tree is literal and `Menu::application()` marks the application menu. This replaces "each adapter places standard items", which is what you were told | no menu appears that you did not write |
| 9 | Config keys stay `windows.default_menu` and `windows.menus`; topside ships no profile | nobody asked for a rename; nothing forced |
| 10 | The four steps: window, route, controller, view; `Route::get()->menu()`, `Menus::page()`, `Menus::window()` | render time wins, as you said |
| 11 | No `->onMac()` / `->offMac()` filters, though the existing code has them | you did not ask for them; they can be added later |
| 12 | `Menus::sharedWith('main')` follows the opener's bar live | a one-time copy goes stale |
| 13 | A window opened with no `menu:` shows the default profile, not its opener's bar | "dev's choice" means the author says so |
| 14 | The Sharp `@menu` block, its tags, `extend` and `@menu replace` | Sharp as a full place to define menus |
| 15 | A toggle's mark changes at once on a click; a new definition moves it only when its value changed | the rule elements already follow |
| 16 | A modal child shows its opener's bar disabled except the edit items; built when child windows are | as the retired bars did |
| 17 | The runtime contract: single operations to the adapter, never a rebuild | a rebuild closes an open menu |
| 18 | Shortcut details: `Shortcut::of('Primary+Shift+S')` for Sharp, the key enum, the two errors | |
| 19 | Approving this document approves stages 1 to 3; stage 4 needs its own design | |

[^element]: Element (apply)
[^mounter]: Mounter (reconcile)
[^driver]: PresentationTreeDriver
[^navigator]: Navigator
[^signals]: LoadSignals
[^mail]: Mail (names in use today)
[^sharp]: Sharp
[^config-cache]: ConfigCacheCommand (var_export)
[^dispatcher]: SignalDispatcher (listen, wildcards)
[^rejected]: The rejected menu model
[^old-bars]: The retired AppKit bar and its siblings
[^raven-stub]: Rmenu_bar_set_native
[^raven-probe]: Raven application menu probe
[^stage1-code]: The menu code committed before this design was approved
