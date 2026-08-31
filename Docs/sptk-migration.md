# SPTK to SPTK App Migration Plan

## Summary

Active SPTK apps detected under `../`: `mademonstrator`, `madventure`, `majira`, `madb`, and `madirector`. The directories `madisplay`, `maduio`, `maditor`, and `madraw` do not currently show SPTK app files at the inspected depth, so they stay out of the first migration wave.

We have enough SPTK foundation to start migrating apps one by one: widgets, `Dock`, dialogs, menus, timers, `GraphicsView`, and texture rendering are now present. The main missing framework piece before serious app migration is key-release input support, because both `madventure` and `madirector` depend on press/release behavior.

## Migration Order

1. `mademonstrator`: simplest entrypoint, no timer or loop callback; good first validation of PHP-built layouts and style replacement.
2. `madventure`: validates animation timer, key press/release, `GraphicsView`, and texture rendering.
3. `majira`: validates normal app controller flow with loop/timer behavior and business UI screens.
4. `madb`: larger app with job polling, shutdown handling, editor/table style workflows, and existing tests.
5. `madirector`: hardest app because of terminal rendering, dynamic timer settings, raw SDL use, and custom terminal/cursor classes.

## Required SPTK Prep

- Add key-release support to SPTK input:
  - Add SDL `KEY_UP` event mapping in `SDLWrapper`.
  - Extend `InputEvent` with a distinct key-up event shape, for example `InputEvent::keyRelease(...)`.
  - Route key-up through `SdlWindow` to focused/root elements consistently with key-down.
  - Add tests covering key-down and key-up dispatch.
- Do not add an XML/XSS compatibility layer.
  - Each app gets a PHP layout builder that returns the root widget and a typed app state/reference object.
  - XML `name` values become explicit references, not global lookups.
- Do not reintroduce old global element lookup APIs.
  - Replace `Element::byName`, `firstByType`, `allByType`, `refresh`, and `immediateRender` with explicit references, widget setters, and normal invalidation/render flow.

## Reusable Per-App Method

For each app, use the same migration sequence:

1. Inventory the old app:
  - Entry callback list from `new SPTK\App(...)`.
  - XML layouts and XSS files.
  - Timers, loops, shutdown hooks, global element lookups, custom `SPTK\Element` subclasses, old `SPTK\Texture` use, raw SDL access, and tokenizer subclasses.
2. Build the SPTK shell:
  - Create a new SPTK entrypoint beside the old one.
  - Build root layout in PHP using `Dock`, menu/status widgets, dialog layer, and app-specific panels.
  - Keep the old entrypoint working until the SPTK version reaches parity.
3. Port layout and style:
  - Translate XML structure into PHP builders.
  - Translate XSS intent into widget/theme options only where visible behavior requires it.
  - Preserve stable logical names as keys in an app state object.
4. Port controller flow:
  - Old init callback becomes app bootstrap after root construction.
  - Old timer callback becomes `SdlApp::addTimer(...)`.
  - Old loop callback becomes `addTicker(...)` only when it must run every event-loop cycle; otherwise use a short repeating timer.
  - Old dynamic wait-time behavior maps to `setMaxEventWait(...)` and `setTimerInterval(...)`.
5. Port custom rendering:
  - Use existing SPTK widgets where possible.
  - Use `GraphicsView` plus `TextureRenderTarget::createTexture(...)` for map, sprite, preview, or atlas-style rendering.
  - For complex controls like `madirector` terminal, create a dedicated SPTK widget rather than forcing it through generic views.
6. Port tokenizers and editor integrations:
  - Convert old `SPTK\Tokenizer` subclasses to the SPTK tokenizer/editor API where possible.
  - If an app has unusual tokenizer behavior, keep it app-local and adapt only the boundary used by SPTK widgets.
7. Validate and switch:
  - Run SPTK tests after framework changes.
  - Run app-specific tests where present, especially `madb/Test`.
  - Manually smoke-test the migrated app UI, menu/dialog behavior, keyboard behavior, timers, rendering, and shutdown.
  - Switch the main entrypoint only after parity is confirmed.

## App-Specific Notes

- `mademonstrator`: start here; migrate layout and markdown/presentation behavior, then verify no timer/loop behavior was accidentally introduced.
- `madventure`: use `GraphicsView` for the game area; convert map/tile/entity texture code to SPTK `Texture`; use key-up support for movement state; animation uses `addTimer`.
- `majira`: preserve init, loop, and timer callback semantics; decide during migration whether the old loop is a ticker or a repeating timer based on observed behavior.
- `madb`: preserve early job-handler initialization and shutdown; map job result polling to a timer/ticker; keep existing tests runnable after autoload migration.
- `madirector`: migrate last; terminal should become a purpose-built SPTK widget with explicit rendering and dynamic timer control via `setTimerInterval`.

## Test Plan

- Add/keep SPTK framework tests for timers, texture drawing, and key-up input.
- For each migrated app, run syntax checks on changed PHP files.
- Run `php Tests/run.php` in SPTK after framework changes.
- Run app-local tests where available, especially `madb/Test`.
- Manually verify each migrated app starts, menus/dialogs work, keyboard input works, timers update, rendering stays inside the app surface, and shutdown hooks run.

## Assumptions

- Migration is one app at a time, not a bulk namespace replacement.
- Old SPTK stays available until each app's SPTK entrypoint is accepted.
- Empty or fileless sibling app directories are ignored until real SPTK app content appears.
- SPTK remains explicit and PHP-built; no old XML/XSS runtime compatibility layer and no old global element registry are added.
