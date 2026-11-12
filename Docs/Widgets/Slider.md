# Slider

`<Slider>` is an interactive bar for a numeric value, with an optional title above
it. Return activates it. Up/Down change the value by the normal step (10 by
default), while Left/Right use the small step (1 by default). Home/End choose
the minimum or maximum (0/100 by default). Return or Escape leaves input mode. A user change emits
`change`; calling `setValue()` from PHP does not.

```xml
<Slider id="volume" value="60" min="0" max="100" step="10" smallStep="1"
        title="SOUND" label="MASTER" unit="%">
  <Event type="change" action="Controller::volumeChanged" />
  <Event type="keyDown" key="m" action="Controller::toggleVolumeMute" />
</Slider>
```

`value` and `min` default to 0, `max` to 100, `step` to 10, and `smallStep` to 1.
The range must increase and both steps must be positive. Values are clamped to the range. The
`title` adds a row above the control in the inherited title color. `label`
appears directly before the bar in the normal text color, and the current value
with its unit appears after it. The bar uses the inherited `Selected` color for
filled cells and a slightly darker shade of `Background` for empty cells.
`muted="true"` starts the slider in a gray display state without changing its
numeric value. Applications can bind an active key such as `m` to a widget event
and update the underlying device's mute state in that action.

In PHP, `getValue()` or `value()` reads the value; `setValue()`, `setLabel()`,
`setUnit()`, `setTitle()`, and `setMuted()` update it without a user change event.
`isMuted()` reads the muted display state. Pass `null`
to `setTitle()` to remove the title. The slider has a preferred height of one
row, or two rows with a title. Nested `Style` and `Event` elements are supported.
