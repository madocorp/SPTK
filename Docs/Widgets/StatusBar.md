# StatusBar

Add `<StatusBar height="1" />` to a screen. It starts empty and does not change when
tile focus moves. Press H to show the selected tile's `tip` or its SPTK default help.
The bar takes focus while help is shown; Return or Esc dismisses it and restores the
previous tile, including its active input mode. The bar dims when another tile is
selected. Arrow navigation skips it by default; set `navigate="true"` to include it.

Color and input behavior are independent. Each style method accepts a behavior as
its second argument: `modal` (the default), `continuous`, or `background`.
Background messages also accept a lifetime in milliseconds as the third argument
(default: 3000). The `display($text, $style, $behavior, $durationMs, $lock)` method
offers the same choices without a style-specific method.

| Style method | Appearance |
| --- | --- |
| `hint($text)` or `notice($text)` | Normal colors |
| `info($text)` | Highlight background |
| `warning($text)` | Selected background |
| `error($text)` | Red background |

| Behavior | Input behavior |
| --- | --- |
| `modal` | Selects the bar; Return or Esc acknowledges, then restores previous focus |
| `continuous` | Stays visible without taking focus; returns after other messages |
| `background` | Does not take focus; disappears after its lifetime, restoring any continuous message |
| `confirmation` | Y or Return accepts; N or Esc declines; other input is drained |

Use `confirm($text, $yes, $no, $legacyCancel = null, $style = 'warning')` for a
confirmation. The optional fourth callback keeps the old separate Esc cancel action.
For a synchronous job, `info($text, 'modal', lock: true)` drains all input until
the application replaces or clears it. `clear()` also removes a continuous message.
`kind()` reports the color style and `behavior()` reports the input behavior.
