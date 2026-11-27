---
type: Module
title: Primitives
description: The TK primitive classes - state and rules shared by every toolkit, the factories, column/row/fixed layout, label and button - and the hooks each adapter fills in.
resource: src/Surface/Toolkits/UI/
tags: [topside, toolkits, primitives]
status: draft
generated: { by: claude-opus/5.5, at: 2026-11-27T00:10:00Z }
sources:
  - id: primitive
    resource: src/Surface/Toolkits/UI/TKPrimitive.php
    title: TKPrimitive
  - id: group
    resource: src/Surface/Toolkits/UI/TKPrimitiveGroup.php
    title: TKPrimitiveGroup
  - id: layout
    resource: src/Surface/Toolkits/UI/TKGroup.php
    title: TKGroup
  - id: contracts
    resource: src/Surface/Contracts/Toolkits/UI/
    title: TK contracts
---

# Two layers

Each primitive is a Topside class holding state and rules, extended by an adapter class that builds the native widget and fills in hooks. Getters answer from the Topside state, never from the toolkit, so they answer the same on every toolkit.[^primitive]

# Classes

| Class | Holds | Adapter hooks |
|---|---|---|
| `TKPrimitive` | name, uuid, window, parent, placement; visible, enabled, opacity, corner radius (`Corners`, each corner its own), clip, transitions, size watching, minimum and maximum size, fill and alignment (`minimumSize()`, `maximumSize()`, `fills()`, `aligned()` for containers); margin (`Insets`), cursor, tooltip, border width and colour. `path()` is walked up the tree each time. Context menus refuse: `ContextMenu` is an empty interface | `bootstrapNative()`, `releaseNative()`, `nativeFocus()`, `applyVisible()`, `applyEnabled()`, `applyOpacity()`, `applyBackground()`, `applyCornerRadius()`, `applyFill()`, `applyAlign()`, `applyMinSize()`, `applyWatchSize()` (start or stop hearing the native's resizes, calling `sizeChanged()`, which posts `ViewResized`); plus the contract's `hasFocus()` and `size()`. Optional (see below): `applyMargin()`, `applyCursor()`, `applyTooltip()`, `applyBorder()`, `applyCorners()`, `applyClip()`, `applyMaxSize()` |
| `TKPrimitiveGroup` | children by name and in order; `view('a.b')`; factories `label()`, `button()`, `column()`, `row()`, `fixed()`, which build the class the toolkit driver's primitive map names; `setClickable()`: a primary press and release on the group or on passive content in it posts `GroupClicked` (x, y from its top-left); a control inside keeps its own click; nothing while disabled | `attachNative()`, `detachNative()`; optional `applyClickable()` (listen for clicks; the toolkit calls `groupClicked(x, y)`); optional `childLayoutChanged()` (a child's fill, align or minimum size changed, before the child applies it) and `childVisibilityChanged()` (after a child was shown or hidden) |
| `TKGroup` | layout containers: moving a child to another position | `reorderNative()` |
| `TKColumn`, `TKRow` | spacing and padding (`Insets`, each side its own, live), from the `LinesUpChildren` trait; homogeneous, every child the same length (`SharesLengthEvenly`) | `applySpacing()`, `applyPadding()`. Optional: `applyHomogeneous()` |
| `TKOverlay` | children on top of each other, the first at the back; each placed by its own align and margin; padding (`Insets`, live). `sizesOverlay()` names the children that give it its natural size: every child but one that fills both ways | `attachNative()`, `detachNative()`, `reorderNative()`, `applyPadding()` |
| `TKFixed` | `at()` (the frame the next child takes, used once), `move()`, `resize()`, `frameOf()` | `applyFrame()` |
| `TKLabel` | text, or runs (`TextRun`: bold, italic, a colour, over the label's own font and colour; `text()` is their text joined, `setText()` makes it one plain run), alignment (left until set: GTK centres a label on its own, the others do not), truncation (`setTruncate()`: one line cut at the end with "…", never wider than its room, whatever wrap says; `wraps()` and `truncates()` read back what was set) | `applyText()`, `applyWrap()`, `applyTruncate()`, `applyAlignment()`, `applyFont()`, `applyTextColor()`. Optional: `applyRuns()`, which shows the joined text plainly until overridden |
| `TKButton` | label, style (`ButtonStyle`: DEFAULT, BORDERLESS, LINK, HELP, the toolkit's help button or DEFAULT where it has none; `framed()` for DEFAULT and HELP), icon file and symbol name, the icon's side (`IconPosition`, leading until set), role (`ButtonRole`), padding (`Insets`, none until set: the toolkit's own); `clicked()` posts `ButtonClicked`. A `LINK` button shows `Cursor::POINTER` unless a cursor was set (`cursorShown()`) | `applyLabel()`, `applyPadding()` (`Insets::none()` gives the toolkit's own back), and calling `clicked()` from the native click. Optional: `applyStyle()`, `applyFont()`, `applyTextColor()`, `applyAlignment()`, `applyIcon($file, $symbol)` (a file wins), `applyIconPosition()`, `applyRole()` |
| `TKCheckbox` | label, checked; `toggled($checked)` posts `Toggled` | `applyLabel()`, `applyChecked()`, and calling `toggled()` from the native signal |
| `TKToggle` | on; `toggled($on)` posts `Toggled` | `applyOn()`, and calling `toggled()` from the native signal |
| `TKDropdown` | options, selected (-1 exactly when there are none; new options keep it while in range, else 0); `chosen($index)` posts `SelectionChanged`; icons by index (`['file' => ?string, 'symbol' => ?string]` or null; the file shows when there is one, else the symbol, as on a button; kept when the options change; `iconOf($index)` says which); icon-only (no border, the selected option's icon alone on its face; the list keeps icons and labels) | `applyOptions($options, $selected)`, `applySelected()`, `applyIcons($icons)` (also after `applyOptions()` while there are icons), `applyIconOnly($on)`, and calling `chosen()` from the native signal |
| `TKTextInput` | value, placeholder, secret and search (fixed, not both), borderless, read-only, maximum length; `typed($value)` posts `TextChanged`, `submitted($value)` `TextSubmitted` | `applyValue()`, `applyPlaceholder()`, `applyFont()`, `applyTextColor()`, `applyAlignment()`, and calling both from the native. Optional: `applyBorderless()`, `applyReadOnly()`, `applyMaxLength()`; `isSearch()` is read in `bootstrapNative()` |
| `TKComboBox` | value, options, completions (only with focus, cleared when hidden); `typed()`, `submitted()`, `choseOption($index)`, `choseCompletion($text)` (index -1), `completionsHidden()` | `applyValue()`, `applyOptions()`, `applyCompletions()` ([] hides), and calling those from the native |
| `TKDatepicker` | a day (midnight in PHP's zone; years 100 to 9999); with none the native shows the computer's today; `picked($year, $month, $day)` posts `DateChanged` | `applyDate()`, and calling `picked()` from the native |
| `TKLink` | text, URL (a scheme, no spaces); posts nothing | `applyText()`, `applyUrl()`, `applyFont()`, `applyAlignment()`; optional `applyTextColor()`; the toolkit opens the URL |
| `TKSeparator` | which way it lies | none |
| `TKProgressBar` | fraction, 0 to 1; null: not known | `applyFraction()` |
| `TKSpinner` | spinning, from the start unless told | `applySpinning()` |
| `TKSlider` | value inside min and max; `slid($value)` posts `ValueChanged` | `applyValue()`, `applyRange($min, $max, $value)`, and calling `slid()` |
| `TKToggleButton` | label, pressed; `toggled()` posts `Toggled`. No image, as `TKButton` | `applyLabel()`, `applyPressed()`, and calling `toggled()` |
| `TKSegmentedControl` | segments, selected (as a dropdown's); `chosen()` posts `SelectionChanged` | `applySegments($segments, $selected)`, `applySegment()`, `applySelected()`, and calling `chosen()` |
| `TKImage` | file (refused here unless readable; kept only once the toolkit took it), scaling | `applyFile()`, which throws for what is no picture, `applyScaling()` |
| `TKWebView` | a page by absolute URL (`load()`) or HTML with an absolute base URL (`loadHtml()`); `url()`, `title()`, `isLoading()` read the engine; `loaded($url)` posts `WebViewLoaded`, `failed($url, $reason)` `WebViewFailed`. Made on every toolkit: one with no web engine answers `supported()` false, and each load posts `WebViewFailed` with `TKWebView::NO_ENGINE` without reaching it | `applyLoad()`, `applyLoadHtml()`, `applyReload()`, `applyStop()`, `url()`, `title()`, `isLoading()`, and calling `loaded()` / `failed()` from the engine's report; `supported()` overridden to false where there is no engine |
| `TKVideo` | audio or video from an absolute path or an absolute URL, which streams (`setSource()`; a path reaches the adapter as its `file://` URL, null unloads); `play()`, `pause()`, `seek()` (0 or later) only with a source; `setMuted()`, `setLoop()`, `setControls()` (the toolkit's own transport) kept and handed over on a change; `position()` and `duration()` read the player, 0 and null with no source; `isPlaying()` is the last report. `playing()` and `paused()` post `VideoPlaying` and `VideoPaused` once per start and stop, `ended()` `VideoEnded`, `failed($reason)` `VideoFailed`. Made on every toolkit: one with no player answers `supported()` false, and each source posts `VideoFailed` with `TKVideo::NO_PLAYER` without reaching it | `applySource($url)` (loads paused at the start, with the muting, looping and controls kept), `applyPlay()`, `applyPause()`, `applySeek()`, `applyMuted()`, `applyLoop()`, `applyControls()`, `readPosition()`, `readDuration()`, and calling `playing()` / `paused()` / `ended()` / `failed()` from the player's report; `supported()` overridden to false where there is no player |

| `TKScrollView` | which ways it scrolls (one at least); holds one container; `scrollPosition()` (a `ScrollPosition`), `watchScroll()`: while on, the user's scrolls post `ViewScrolled`, the latest per pump (`scrolled()`, called from the native's scroll callback; needs `nativeScrollPosition()`, optional `applyWatchScroll()`) | `attachNative()`, `detachNative()`, `applyScrollbars()` |
| `TKTabView` | pages (columns, by `addPage()`), titles, which is selected; `chosen($index)` posts `TabSelected` | `reorderNative()`, `applyTitle()`, `applySelected()`, and calling `chosen()` |
| `TKSplitView` | two panes, the divider's fraction; `dragged($position)` posts `SplitMoved` | `reorderNative()`, `applyPosition()`, and calling `dragged()` |
| `TKGrid` | spacing, padding (as a column's), each child's cell (`cellOf()`, `arrangeCells()` all at once) | `applyCells()`, `applySpacing()`, `applyPadding()` |
| `TKFlow` | children at their own size, wrapping onto the next line where the width runs out, lines from the left; spacing (on a line and between lines) and padding, as a column's | `attachNative()`, `detachNative()`, `reorderNative()`, `applySpacing()`, `applyPadding()`; the toolkit's flow layout, or the adapter places the children itself |
| `TKTextArea` | text, read-only, wrap, tab width, alignment, line and range styles; `typed()` posts `TextChanged`, `scrolled()` `ViewScrolled` while watched | an `apply*()` for each, `nativeSelectedLines()`, `nativeScrollPosition()` |
| `TKListView`, `TKCollectionView` (both `TKItemView`) | every item, row height or cell size; `activated()`, `actionClicked()` post for enabled items, `scrolled()` while watched | `applyItems()`, `applyInserted()`, `applyRemoved()`, `applyUpdated()`, scroll, and the size's `apply*()` |
| `TKTable` | columns (fixed), rows as text by column id, selection; `choseRow()` posts `RowSelected` | `applyRows()`, `applySelectedRow()` |
| `TKTree` | columns (fixed), nodes, which are open, selection; `choseNode()`, `openedNode()`, `activatedNode()` post | `applyNodes()`, `applyExpanded()` (only under open ancestors), `applySelected()` |

A container expands along an axis when it fills or anything shown inside it does (`expands()`); a toolkit that keeps fill on the container's layout tells the one above with `tellExpansion()`. `TKScrollView` extends `TKPrimitiveGroup` directly; the rest are `TKGroup`s, whose children keep an order.[^layout]

# Optional hooks

A hook with a body in the TK class (margin, cursor, tooltip, border; a button's style, font, colour, alignment, icon and role; a text input's borderless, read-only and maximum length; a link's colour) does nothing until an adapter overrides it. An adapter overrides one only where its toolkit can show the setting natively; elsewhere the setting is silently not shown, and the getter still answers what was set. Nothing is drawn or composed in its place.

`setMargin()` tells the container first (`childLayoutChanged()`), then `applyMargin()`: some toolkits keep the space round a child in the container's layout, some on the child. `Insets` (`Contracts\Toolkits\UI`) is one to four values in CSS order: `16`, `'40 16'`, `'40 16 8'`, `'40 16 8 0'`.

`setCornerRadius()` takes `Corners` (`Contracts\Toolkits\UI`): one to four radii in CSS `border-radius` order, top-left, top-right, bottom-right, bottom-left, so `'0 0 14 14'` is square on top. `cornerRadius()` answers the largest; `corners()` all four. It reaches the toolkit as `applyCorners()`, whose default hands `applyCornerRadius()` the one radius when the corners agree and 0 when they differ: rounding every corner would be wrong. A toolkit that rounds corners apart overrides `applyCorners()`. `setClip()` cuts what is inside at the edge and its corner radius (`applyClip()`, nothing by default); a toolkit that can only clip to the rectangle clips to the rectangle.

`appear()` and `disappear()` take a `TKTransition` and seconds (default `TKTransition::DEFAULT_SECONDS`, 0.3). `FADE` fades; `SLIDE_*` fade and slide `SLIDE_DISTANCE` points. `MOVE_RIGHT`, `MOVE_LEFT`, `MOVE_TOP`, `MOVE_BOTTOM` (SwiftUI's `move(edge:)`) travel the primitive's own full width or height from that edge, with no fade, and back out to it on `disappear()`: an off-canvas drawer. `edge()` gives the start in the primitive's own size (`MOVE_RIGHT` is `[1, 0]`); `curve()` the cubic Bézier every toolkit plays it on: `MOVE_IN_CURVE`, easeOutQuint `(0.22, 1, 0.36, 1)`, appearing, and its mirror `MOVE_OUT_CURVE`, easeInQuint, disappearing; the adapter reads the laid-out size when it plays. `applyVisible()` receives the transition; a toolkit that cannot move a view by its own size shows and hides it at once.

# Rules (the same on every toolkit)

* Every refusal comes before a native exists: an empty name, a dotted name, a sibling's name, a kind the toolkit lacks, negative spacing or padding, a fixed child without `at()`, a text input both secret and search.
* A negative side of padding or margin, a negative border width, or a maximum length below 1 is refused by its setter, which then changes nothing.
* A toolkit that cuts a field's text to its maximum length itself calls `cutTo()` from `applyValue()` or `applyMaxLength()`: `value()` then answers what the field holds, and nothing is posted. A window's content column takes `Insets` padding as any column does.
* Every setter throws once removed. `remove()` removes children first (last first), marks the primitive removed before its native goes, and frees the name.
* `setEnabled()` stores the primitive's own flag and tells only its own native; the toolkit carries a container's state down (AppKit, which cannot, does it in its own package).
* A check box, switch or dropdown posts only when the native reports a value other than the one topside holds. Setters store the value before telling the native, so a toolkit that signals changes made from code (GTK, Qt) posts nothing for them. A dropdown also ignores an index out of range.
* The text input, combo box, date picker, slider and segmented control write to the native inside `write()` (`IgnoresItsOwnWrites`): what a toolkit signals meanwhile, in steps or not, is not the user's.
* `ToolkitWindow::settleFocus()` calls `focusMovedAway()` on the primitive focus left. The combo box hides its completions there: an inactive window moves focus with no focus-out from Qt or GTK.
* `TKButton::clicked()` posts nothing while the button or a container above it is disabled: toolkits block the user's clicks there, not every toolkit blocks clicks made from code.
* `hasFocus()` means "this widget is its window's focus", on every toolkit.
* `focus()` refuses when not shown (it or a container above hidden), disabled (it or a container above), or when `nativeFocus()` answers false. It reads the Topside flags, since Raven's public API and AppKit's containers cannot answer for a container above.
* `moveBefore()`, `moveAfter()`, `moveTo()` work in every `TKGroup` but a fixed (`keepsOrder()`).
* A drag's mail (`ValueChanged`, `SplitMoved`) goes by `postLatest()`: one per pump, the last. AppKit does not return from the pump during a drag, so there it is one per drag.

* A tree node closed over the selection clears it, on every toolkit: some drop it themselves and some keep it out of sight. The user's close posts `NodeExpanded` and `NodeSelected(null)`; code's posts nothing.

# Contracts trimmed for now

The group contract's factories are trimmed to `label`, `button`, `checkbox`, `toggle`, `dropdown`, `link`, `textInput`, `comboBox`, `datepicker`, `separator`, `progressBar`, `spinner`, `slider`, `toggleButton`, `segmentedControl`, `image`, `column`, `row`, `grid`, `scrollView`, `split`, `tabs`, `textArea`, `listView`, `collectionView`, `table`, `tree`, `fixed`; the button contract has its label, style, font, colour, alignment, icon, symbol and role; `HasButtonImage` (a framebuffer image) is not used.[^contracts]

# Checked

61 checks against fake subclasses that only record what each hook is told. All four toolkits have concrete primitives built on these classes (`jovian/venusian-gtk`, `-qt`, `-appkit`, `-raven`, each `src/UI/`); each passes the same 35-check real-window harness through its own classes (2026-11-21).

[^primitive]: TKPrimitive
[^group]: TKPrimitiveGroup
[^layout]: TKGroup
[^contracts]: TK contracts
