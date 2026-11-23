---
type: Module
title: Primitives
description: The TK primitive classes - state and rules shared by every toolkit, the factories, column/row/fixed layout, label and button - and the hooks each adapter fills in.
resource: src/Surface/Toolkits/UI/
tags: [topside, toolkits, primitives]
status: draft
generated: { by: claude-opus/5.5, at: 2026-11-21T18:58:43Z }
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
| `TKPrimitive` | name, uuid, window, parent, placement; visible, enabled, opacity, corner radius, transitions, size watching, minimum size, fill and alignment (`minimumSize()`, `fills()`, `aligned()` for containers). `path()` is walked up the tree each time. Context menus refuse: `ContextMenu` is an empty interface | `bootstrapNative()`, `releaseNative()`, `nativeFocus()`, `applyVisible()`, `applyEnabled()`, `applyOpacity()`, `applyBackground()`, `applyCornerRadius()`, `applyFill()`, `applyAlign()`, `applyMinSize()`; plus the contract's `hasFocus()` and `size()` |
| `TKPrimitiveGroup` | children by name and in order; `view('a.b')`; factories `label()`, `button()`, `column()`, `row()`, `fixed()`, which build the class the toolkit driver's primitive map names | `attachNative()`, `detachNative()`; optional `childLayoutChanged()` (a child's fill, align or minimum size changed, before the child applies it) and `childVisibilityChanged()` (after a child was shown or hidden) |
| `TKGroup` | layout containers: moving a child to another position | `reorderNative()` |
| `TKColumn`, `TKRow` | spacing and padding, from the `LinesUpChildren` trait | `applySpacing()` |
| `TKFixed` | `at()` (the frame the next child takes, used once), `move()`, `resize()`, `frameOf()` | `applyFrame()` |
| `TKLabel` | text, alignment (left until set: GTK centres a label on its own, the others do not) | `applyText()`, `applyWrap()`, `applyAlignment()`, `applyFont()`, `applyTextColor()` |
| `TKButton` | label; `clicked()` posts `ButtonClicked` | `applyLabel()`, and calling `clicked()` from the native click |
| `TKCheckbox` | label, checked; `toggled($checked)` posts `Toggled` | `applyLabel()`, `applyChecked()`, and calling `toggled()` from the native signal |
| `TKToggle` | on; `toggled($on)` posts `Toggled` | `applyOn()`, and calling `toggled()` from the native signal |
| `TKDropdown` | options, selected (-1 exactly when there are none; new options keep it while in range, else 0); `chosen($index)` posts `SelectionChanged` | `applyOptions($options, $selected)`, `applySelected()`, and calling `chosen()` from the native signal |
| `TKTextInput` | value, placeholder, secret (fixed); `typed($value)` posts `TextChanged`, `submitted($value)` `TextSubmitted` | `applyValue()`, `applyPlaceholder()`, `applyFont()`, `applyTextColor()`, `applyAlignment()`, and calling both from the native |
| `TKComboBox` | value, options, completions (only with focus, cleared when hidden); `typed()`, `submitted()`, `choseOption($index)`, `choseCompletion($text)` (index -1), `completionsHidden()` | `applyValue()`, `applyOptions()`, `applyCompletions()` ([] hides), and calling those from the native |
| `TKDatepicker` | a day (midnight in PHP's zone; years 100 to 9999); with none the native shows the computer's today; `picked($year, $month, $day)` posts `DateChanged` | `applyDate()`, and calling `picked()` from the native |
| `TKLink` | text, URL (a scheme, no spaces); posts nothing | `applyText()`, `applyUrl()`, `applyFont()`, `applyAlignment()`; the toolkit opens the URL |
| `TKSeparator` | which way it lies | none |
| `TKProgressBar` | fraction, 0 to 1; null: not known | `applyFraction()` |
| `TKSpinner` | spinning, from the start unless told | `applySpinning()` |
| `TKSlider` | value inside min and max; `slid($value)` posts `ValueChanged` | `applyValue()`, `applyRange($min, $max, $value)`, and calling `slid()` |
| `TKToggleButton` | label, pressed; `toggled()` posts `Toggled`. No image, as `TKButton` | `applyLabel()`, `applyPressed()`, and calling `toggled()` |
| `TKSegmentedControl` | segments, selected (as a dropdown's); `chosen()` posts `SelectionChanged` | `applySegments($segments, $selected)`, `applySegment()`, `applySelected()`, and calling `chosen()` |
| `TKImage` | file (refused here unless readable; kept only once the toolkit took it), scaling | `applyFile()`, which throws for what is no picture, `applyScaling()` |

| `TKScrollView` | which ways it scrolls (one at least); holds one container | `attachNative()`, `detachNative()`, `applyScrollbars()` |
| `TKTabView` | pages (columns, by `addPage()`), titles, which is selected; `chosen($index)` posts `TabSelected` | `reorderNative()`, `applyTitle()`, `applySelected()`, and calling `chosen()` |
| `TKSplitView` | two panes, the divider's fraction; `dragged($position)` posts `SplitMoved` | `reorderNative()`, `applyPosition()`, and calling `dragged()` |
| `TKGrid` | spacing, padding, each child's cell (`cellOf()`, `arrangeCells()` all at once) | `applyCells()`, `applySpacing()` |
| `TKTextArea` | text, read-only, wrap, tab width, alignment, line and range styles; `typed()` posts `TextChanged`, `scrolled()` `ViewScrolled` while watched | an `apply*()` for each, `nativeSelectedLines()`, `nativeScrollPosition()` |
| `TKListView`, `TKCollectionView` (both `TKItemView`) | every item, row height or cell size; `activated()`, `actionClicked()` post for enabled items, `scrolled()` while watched | `applyItems()`, `applyInserted()`, `applyRemoved()`, `applyUpdated()`, scroll, and the size's `apply*()` |
| `TKTable` | columns (fixed), rows as text by column id, selection; `choseRow()` posts `RowSelected` | `applyRows()`, `applySelectedRow()` |
| `TKTree` | columns (fixed), nodes, which are open, selection; `choseNode()`, `openedNode()`, `activatedNode()` post | `applyNodes()`, `applyExpanded()` (only under open ancestors), `applySelected()` |

A container expands along an axis when it fills or anything shown inside it does (`expands()`); a toolkit that keeps fill on the container's layout tells the one above with `tellExpansion()`. `TKScrollView` extends `TKPrimitiveGroup` directly; the rest are `TKGroup`s, whose children keep an order.[^layout]

# Rules (the same on every toolkit)

* Every refusal comes before a native exists: an empty name, a dotted name, a sibling's name, a kind the toolkit lacks, negative spacing or padding, a fixed child without `at()`.
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

The group contract's factories are trimmed to `label`, `button`, `checkbox`, `toggle`, `dropdown`, `link`, `textInput`, `comboBox`, `datepicker`, `separator`, `progressBar`, `spinner`, `slider`, `toggleButton`, `segmentedControl`, `image`, `column`, `row`, `grid`, `scrollView`, `split`, `tabs`, `textArea`, `listView`, `collectionView`, `table`, `tree`, `fixed`; the button contract to `label()` and `setLabel()` (its image, font, colour, alignment and role are commented out until needed).[^contracts]

# Checked

61 checks against fake subclasses that only record what each hook is told. All four toolkits have concrete primitives built on these classes (`jovian/venusian-gtk`, `-qt`, `-appkit`, `-raven`, each `src/UI/`); each passes the same 35-check real-window harness through its own classes (2026-11-21).

[^primitive]: TKPrimitive
[^group]: TKPrimitiveGroup
[^layout]: TKGroup
[^contracts]: TK contracts
