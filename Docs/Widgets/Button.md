# Button

`<Button>` is a one-row widget. Its preferred width includes the label, the optional hotkey and separating
space, and one space of padding on each side. Return runs its action immediately; a button never enters input mode.

```xml
<Button id="save" label="Save" hotkey="f6" action="Controller::save" />
```

`opens="#id"` shows a hidden [overwrite](../layout.md#overwrite) element. `restores="parent"` puts back
the nearest shown overwrite element around the button; `restores="#id"` puts back a named one. On press
the button first shows, then runs its action, then restores, so a Save action can still read the form.

`label` is required. `action` is required unless `opens` or `restores` is set. `action` names a public static method that receives an
`SPTK\Events\EventContext`. The context's `widget` is the button; `input` is the triggering key event.
`hotkey` is optional and accepts a key name or a chord such as `ctrl+s` or `ctrl+shift+s`.
It appears before the label in the highlight color. Control and Alt chords work even while
another widget is active; plain typing keys remain available to an active text widget. Hotkeys
must be unique among buttons on a screen. `width` can override the preferred width in a horizontal layout;
`height` can override the preferred height in a vertical layout.

The pipe symbol (`hotkey="|"`) is also accepted. It runs from typed text, so it follows the active
keyboard layout and remains available when another control on the screen is selected.

Single-letter and digit hotkeys wait for SDL text input before running their action. An active `Input` or
`TextEditor` receives the character first. If SDL produces no text input, the action runs on key release.
Other hotkeys run on keydown. List search keeps its typing keys while active.

The framework uses buttons for `<ScreenSelector>`. Its current-screen button has an `activated` state and
inverted colors in its character grid cells. The surrounding pixel background keeps its normal color.
This state does not mean the button is in input mode.

`setLabel(string $label)` replaces the displayed one-line label and emits `change`.
Use it to show an application toggle's current state.
