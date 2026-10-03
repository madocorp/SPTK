# DateSelector

`<DateSelector>` shows a Monday-first calendar with six weeks, a month heading, and an editable date field. Days from adjacent months are dimmed; the selected day is highlighted.

```xml
<DateSelector value="2024-02-29" id="deadline">
  <Event type="change" action="Controller::dateChanged" />
</DateSelector>
```

`value` defaults to today's local date. It must use a valid `YYYY-MM-DD` date with a year from 0001 to 9999. `getValue()` returns that format, `setValue(string)` changes the date without emitting an event, `dateText()` includes any incomplete entry, and `active()` reports activation.

Return activates the calendar. Left/Right move one day and Up/Down move one week, stopping at the displayed month's edge. Home/End select the first or last day of the month. Page Up/Down move one month, preserving the day where possible; Shift+Page Up/Down move one year. Space selects today. Type eight digits in `YYYYMMDD` order to enter a date. Incomplete or invalid entries leave the selected date unchanged and remain visible for correction with Backspace or Delete. Return or Escape releases the widget, discards an incomplete entry, and keeps the last valid date. Each user change emits `change` once.

The calendar prefers 28 columns and 9 rows. It clips inside smaller tiles. It uses inherited `Foreground`, `Background`, `Selected`, and `CursorBackground` colors and supports nested `<Style>` and `<Event>` declarations. The demo **Colors** screen places it beside ColorSelector.
