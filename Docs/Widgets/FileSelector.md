# FileSelector

`<FileSelector>` browses the filesystem in a scrollable list with the current directory path fixed on the first row in the inherited `Highlight` color. Directories appear before files and have a `/` prefix; files have a leading space. Both groups use natural, case-insensitive sorting. The first entry is `..` for the parent directory, or `/` at the filesystem root.

```xml
<FileSelector path=".." height="1*" id="files">
  <Event type="accept" action="Controller::fileAccepted" />
</FileSelector>
```

`path` defaults to `.` and is resolved relative to the XML screen file. `multiple`, `filterable`, and `searchable` are boolean attributes; their defaults are `false`, `true`, and `true`. The widget inherits its colors from `<Style>`, and accepts nested `<Event>` and `<Style>` declarations.

Return activates the widget. Up/Down, Home/End, and Page Up/Down navigate the list below the fixed path row. Typing searches directory and file names without their display prefixes. When `filterable="true"`, only matching entries remain; when it is false and `searchable="true"`, the list jumps to a match without hiding other entries. Backspace edits the query and Delete clears it. Space toggles the highlighted path in multiple-selection mode.

Return on a directory enters it and emits `change`; entering `..` moves to the parent and leaves the departed directory under the cursor. Return on a file, or on `/` at the root, releases the widget with `accept`. Escape also releases with `accept`. A failed directory entry leaves the current listing in place and shows an error beside the path until a successful navigation or reload. `select` remains the normal tile-focus event; use `accept` to act on a chosen file.

`path()` gives the current directory, `activeValue()` gives the full path under the cursor, and `getValue()` returns that path or an array of selected paths in multiple mode. `items()` and `values()` expose full paths, while `cursorPosition()`, `filter()`, and `active()` describe navigation state. `setPath(string)` and `reload()` read the directory again; `setValue(string|array)` and `setFilter(string)` change selection and search without emitting a user event. A failed `setPath()` throws without replacing the current listing. The demo **Colors** screen shows FileSelector below ColorSelector and DateSelector.
