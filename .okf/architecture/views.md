---
type: Module
title: Views
description: Components that describe a window's content from their state, and the Mounter that turns those descriptions into primitives and changes only what differs after each handler.
resource: src/Surface/Toolkits/Views/
tags: [topside, toolkits, views, components]
status: draft
generated: { by: claude-opus/5.5, at: 2026-11-25T19:15:51Z }
sources:
  - id: component
    resource: src/Surface/Toolkits/Views/Component.php
    title: Component
  - id: elements
    resource: src/Surface/Toolkits/Views/Elements/
    title: Elements
  - id: mounter
    resource: src/Surface/Toolkits/Views/Mounter.php
    title: Mounter
  - id: driver
    resource: src/Surface/Toolkits/Drivers/PresentationTreeDriver.php
    title: PresentationTreeDriver
---

# From a route to the screen

`Navigator::go($route)` calls the route's controller method, which calls `View::render(SomeView::class, $props)`. A name that is no class is a [Sharp](/architecture/sharp.md) template. `PresentationTreeManager::render()` builds the component through the container, sets `$props` on its public properties (`Component::assign()`; an unknown name throws `ViewException`), and hands it to `PresentationTreeDriver::render()`. The driver removes the view on screen, then mounts the new one into the window's content column, `root`, made in `boot()`.[^driver]

A navigator belongs to the window that shows routes, not to the application. `PresentationTreeDriver::navigator()` makes it once and keeps it as long as the presentation, so the current route and the back and forward history last across views. The driver mounts each view with a `Mount` (the navigator, the window, and a refresh): `MountedView` gives it to the page (`Component::mountedIn()`) and to its `Mounter`, which gives it to every component it builds inside, however deep. A component reaches the navigator with `$this->navigator()`, with nothing passed down through props; one that is not mounted throws `ViewException`. Asking the container for `Navigator` gives the same one (a binding to the driver's, not a singleton), so `ToolkitApplication::activate()`, controllers and services share the window's history. 

The same mount gives a component `$this->window()`, the window it is in (to set its title, for one), and `$this->refresh()`, which shows the view again after its state changed outside a handler: a request or a download that came back on the event loop. The driver renders the view again only while it is the view on screen and no handler or render is under way (one that is ends with the view shown anyway); off screen, or not mounted, it does nothing. `mounted()` is called once on the page's component as it is put on screen, before its first render: the place to start what it loads; a view that puts another on screen there is not shown. `$this->alert(Alert, $answered)` asks the window for an alert through the mount; the driver holds the handler under the alert's id and runs it with the `AlertAnswered`, then renders the view again, as after a click. A handler whose view left the screen is dropped. Before this, nothing bound `Navigator`, so every `app(Navigator::class)` was a new one: routing worked, but `current()` was always null and history never lasted.[^driver]

# A component

`Component` subclasses hold state in public properties, act in their own methods, and describe themselves in `render(): Element`. `render()` runs on first mount and again after every handler, so it only reads state.[^component]

# Elements

An element describes one primitive and touches no toolkit. `Column::make(spacing, padding)`, `Row::make(...)`, `Label::make($text)`, `Button::make($label)->onClick(Closure)`, `Checkbox::make($label, checked:)->onToggle(fn (bool $on) => ...)`, `Toggle::make(on:)->onToggle(...)`, `Dropdown::make($options, selected:)->onChange(fn (int $index, string $option) => ...)`, `Link::make($text, $url)`, `TextInput::make(value:, placeholder:, secret:)`, `ComboBox::make($options, value:)->completions([...])`, `Datepicker::make($date)`, `Separator::make(vertical:)`, `ProgressBar::make($value)` (none: indeterminate), `Spinner::make(spinning:)`, `Slider::make($value, min:, max:)->onChange(fn (float $value) => ...)`, `ToggleButton::make($label, pressed:)`, `SegmentedControl::make($options, selected:)` (as a Dropdown), `Image::make($src)->scaling()` (absolute, or under resources/), `ScrollView::make(spacing:, padding:, horizontal:, vertical:)` (any children, in a column of its own), `TabView::make(selected:)` of `Tab::make($title)` (needs no state; a hidden tab is left out), `SplitView::make($position, stacked:)` (two panes), `Grid::make($columns, spacing:)` (flows; `span()` on a child), `TextArea::make($value)`, `ListView::make($items)` and `CollectionView::make($items)` (text, or arrays; changed item by item), `Table::make($rows, columns:, selected:)` (columns default to the first row's keys), `Tree::make($nodes, columns:, selected:, expanded:)` (nested arrays, a node's id its path; or arrays with id, label, children), `Spacer::make()`. Those five fill too. `selected` and `expanded` are optional: left out, they are the user's. Scroll, tab and split views fill unless told `fill(false, false)`. A value widget's handler gets the new value (and the mail last); give the element the state it shows and keep that state in the handler. Shared settings: `visible()`/`hidden()`, `enabled()`/`disabled()`, `fill()`, `align()`, `minSize()`, `background()`, `opacity()`, `cornerRadius()`, `margin()` (one to four values, as CSS), `cursor()` (`Cursor`), `tooltip()`, `border($width, $color)`, `key()`, `name()`. Label adds `wrap()`, `textAlign()`, `font()`, `color()`. Button adds `style()` (`ButtonStyle`: default, borderless, link), `font()`, `color()`, `textAlign()`, `icon()` (a file), `symbol()` (the toolkit's own name), `role()`. TextInput adds `search` to make(), `borderless()`, `readOnly()`, `maxLength()`. Link adds `color()`. Column, Row, Grid, Tab and ScrollView take padding as one to four values (`Column::make(8, '40 16')`); a change is `setPadding()`, not a new primitive. What a toolkit cannot show natively it leaves unshown (see [Primitives](/architecture/primitives.md)).[^elements]

* A text element's `onChange()` gets the value the primitive holds at delivery, not the mail's: mail queues, and older text rendered back would undo what was typed since. A value is written only when the state's value changed and the primitive holds another.
* A setting left unset is the primitive's default, so dropping it between renders sets the default back.
* `->with($a, $show ? $b : null)`: a left-out child holds its place.
* `justify(Justify::CENTER|END)` on a column or row adds keyed spacers (empty columns filling along the axis) before and/or after the children. This is what centres along the axis on every toolkit: each one shares spare room among the children that fill.
* A view's root fills the window unless it calls `fill()` itself.
* What only a constructor takes (padding) or cannot be unset (a label's font) builds the primitive again when it changes.

# The Mounter

`Mounter::reconcile($node, $elements)` matches each element with an existing primitive: by `key()`, otherwise by its place among unkeyed children (holes included). Same class and shape: `apply()` calls only the setters whose value changed. Otherwise build through the group's factories (`label()`, `button()`, `column()`, `row()`), so the toolkit driver's primitive map picks the concrete class. Leftovers are removed before anything is built, freeing their names; each child is then `moveTo()`'d into place if needed, and last the element's `settle()` sets what needs the children there (a tab view's selection, a grid's cells). Generated names are `{kind}{n}` per parent (`root.column1.button3`); `name()` overrides.[^mounter]

`MountedView` holds the tree and a uuid → handler map; `unmount()` removes everything, handlers included.

If a `render()` throws partway, the Mounter's record still holds every primitive on screen (a half-built primitive is removed), so the next render or unmount cleans up.

# Components inside components

`Card::make(title: 'Hi')->with(...)` returns a `ComponentElement`. The Mounter builds the component through the container once, keeps the instance while it is matched (by class, then key or place), and mounts its `render()`'s root in its place, named `{class}{n}` (`card2`).

* Props: the named arguments are assigned on every render of the parent, so they are the parent's. Any other property is the component's own state.
* Slot: what `->with()` gets replaces `Children::slot()` in the component's render. A slot outside any component, or as a component's root, throws.
* Settings made where it is used (`->hidden()`, `->align()`, `->name()`) are laid over its root.
* Upward: a closure prop the parent passes (`onClose: fn () => ...`).
* A root of another kind replaces the primitive but keeps the instance.
* After any handler the whole view renders again, not only the component that owned the handler: a child's handler may change its parent's state through a closure prop. Child instances and their state survive it.

`Toolkits/Demo/Views/Card.php` is the example: a padded, tinted, rounded column with a bold title, a slot and a `width` prop (its minimum: there is no maximum size, so wider content still widens it). `DemoHomeView` gives both of its cards 340, which holds its widest summary line on all four toolkits (measured 2026-11-21).

# Mail

`LoadSignals` listens on `PrimitiveMail` (the dispatcher also dispatches under a signal's interfaces) and calls `PresentationTreeDriver::deliver()`. The driver ignores another window's mail, runs the on-screen view's handler (`ButtonClicked` and `GroupClicked` → `onClick`, `TextChanged` → `onChange`, `TextSubmitted` → `onSubmit`, `DateChanged`, `ValueChanged`, `TabSelected` and `SplitMoved` → `onChange`, selections → `onSelect`, `NodeExpanded` → `onExpand`, activations → `onActivate`, `ItemActionClicked` → `onAction`), then renders the view again. If the handler put another view on screen, the old one is not rendered again. `Navigator::clicked()` (the `on{WidgetName}` convention) is gone.[^driver]

# Checked

34 checks against the fake toolkit's recording primitives (`tests/Unit/Views/`): the demo's tree, a click changing only the label's text with nothing rebuilt, props, unmounting, handler routing (including through signals), defaults restored, spacing vs padding, keyed moves, kind changes, holes, justify spacers, names, duplicate keys; nested components' own state, props, slots, closure props, fresh state after removal, root kind change, keyed moves, settings laid over, refusals, a failed render leaving nothing. Re-rendering and patching 500 keyed labels: about 2.7 ms (Apple silicon, PHP 8.4 NTS). Real windows (2026-11-21, `apps/topside-dev`): the demo card is centred on AppKit, Qt, GTK and Raven; a click updated the label on AppKit, Qt and GTK.

[^component]: Component
[^elements]: Elements
[^mounter]: Mounter
[^driver]: PresentationTreeDriver
