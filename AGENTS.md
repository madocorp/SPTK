# SPTK mad4

A small PHP 8.2+ UI experiment using SDL3 through FFI.
Tileable windows, one widget per tile.


## Predecessor proejects

Don't mix this with the ancestor projects (mad/SPTK, mad2/SPTK, mad3/SPTK). There can be valuable implementations, many of
them is reusable (especially the widget logics). You can use them to support developing, but you need to reimplement these things
in the new environment instead of copy them.  Don't let the user to mix them too.


## Application contract

- Every application entry point defines `APP_DIR` as `__DIR__`. Trust these constants; do not add existence checks or fallbacks.
- Simple apps only need to instantiate SPTK\App, layout is defined in the app.xml file under APP_DIR/Layout directory.
- Each screen has an own xml file.
- The library is available at `APP_DIR/SPTK`; the bundled demo and tests use relative `SPTK -> ..` links to meet this contract.


## Structure

- See Docs/classes.md
- App (EventLoop) -> Windows -> Screens -> Tiles (tree) -> Widgets (one per tile)
- User can move between tiles with arrow, the selected widget is highlighted
- Activate a widget wit return, release it with esc (or return)
- Only the activated widget can receive inputs


## Readability

- Review every class above 300 physical lines; split by responsibility, ask the user.
- Hard limit: 500 physical lines per class.
- Native libraries and generated headers are excluded. Never compress formatting to meet a limit. Run `php Tools/check-size.php`.
- Use two-space indentation and same-line opening braces followed by a newline. Closing braces go on their own line; no inline block bodies, even empty ones.
- Keep block bodies correctly indented. Prefer short, explicit methods.
- Document each class's responsibility in a docblock.
- Dcument each function's purpose in a oneline docblock (< 120 chars)
- no parameters or return values in the docblock, use typehinting
- Use composition and direct calls. Add abstractions only for a concrete need.
- Update Docs if needed
- No empty lines inside functions
- 1 empty lines between functions, and definition blocks
- code order: namespace, (require), use, class variables (static-public-private), static method definitions (public-private), constructor, methods (public-private)
- one class per file
- avoid unnamed functions
