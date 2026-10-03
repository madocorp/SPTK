# Button

`<Button>` is a one-row widget. Its preferred width includes the label, the optional hotkey and separating
space, and one space of padding on each side. Return runs its action immediately; a button never enters input mode.

```xml
<Button id="save" label="Save" hotkey="f6" action="Controller::save" />
```

`label` and `action` are required. `action` names a public static method that receives an
`SPTK\Events\EventContext`. The context's `widget` is the button; `input` is the triggering key event.
`hotkey` is optional and accepts an unmodified key name from the event key list. It appears before the label
in the highlight color and works anywhere on the screen, including when another widget is active. Hotkeys
must be unique among buttons on a screen. `width` can override the preferred width in a horizontal layout;
`height` can override the preferred height in a vertical layout.

Single-letter and digit hotkeys wait for SDL text input before running their action. An active `Input` or
`TextEditor` receives the character first, so the action sees its updated value. If SDL produces no text
input, the action runs on key release. Other hotkeys still run on keydown. List search keeps its own
typing keys while active.

The framework uses buttons for `<ScreenSelector>`. Its current-screen button has an `activated` state and
inverted colors in its character grid cells. The surrounding pixel background keeps its normal color.
This state does not mean the button is in input mode.

`setLabel(string $label)` replaces the displayed one-line label and emits `change`.
Use it to show an application toggle's current state.
