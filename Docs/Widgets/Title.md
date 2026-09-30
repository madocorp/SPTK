# Title

`<Title>` displays one line in the inherited `Highlight` color over the inherited `Background`. It uses the app's normal font. Long titles are clipped to the tile width; they do not wrap or scroll. The preferred height is one row.

```xml
<Title height="1">Settings and about</Title>
<Title id="heading" height="1" tip="Presentation title; edit the Markdown to change it." />
```

Use `setText(string $text)` to update a title. It accepts printable single-line UTF-8 text and emits `change`. A title cannot be activated; arrow keys move between tiles. Its default status bar tip is “Screen title; arrow keys move between tiles.” The shared `tip` XML attribute overrides it. Like other widgets, Title accepts local `<Style>` and `<Event>` children.
