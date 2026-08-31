# SPTK

SPTK is a lightweight graphical user interface toolkit written in PHP. It uses
SDL3 and SDL3_ttf through PHP FFI and exposes a grid-first widget model for
windows, menus, dialogs, text editors, lists, tables, inputs, images, graphics
views, and other reusable interface elements.

New code should use the `SPTK\...` namespace.

## Requirements

Install PHP 8.0 or newer with these CLI extensions enabled:

- FFI
- PCNTL
- Mbstring

SDL rendering uses SDL3 and SDL3_ttf. SDL3_ttf also requires the FreeType and
HarfBuzz runtime libraries.

Check the PHP installation with:

```sh
php --version
php -m | grep -E '^(FFI|mbstring|pcntl)$'
```

On Linux, install missing PHP extensions and runtime libraries through your
distribution package manager. Package names vary, but they are commonly similar
to `php-cli`, `php-ffi`, `php-mbstring`, `libsdl3`, `libsdl3-ttf`, `freetype`,
and `harfbuzz`.

## Installation

SPTK does not use Composer. Clone or copy the repository to a permanent
location, for example:

```sh
mkdir -p ~/.local/share
git clone https://github.com/madocorp/SPTK.git ~/.local/share/SPTK
```

The resulting directory, `~/.local/share/SPTK` in this example, is the SPTK
directory path used by applications that depend on the toolkit.

Applications in the same workspace usually load SPTK from a directory or
symbolic link named `SPTK` next to the application entry point:

```sh
cd /path/to/application
ln -s ~/.local/share/SPTK SPTK
```

The expected layout is:

```text
application/
|-- application.php
|-- SPTK -> /home/user/.local/share/SPTK
`-- ...
```

Use an absolute symlink target when the application and SPTK are installed in
unrelated locations. The link name must be exactly `SPTK`.

## Configure SDL

The current loader reads SDL shared libraries from `SDLWrapper/` using these
filenames:

```text
SDLWrapper/libSDL3.so.0.2.21
SDLWrapper/libSDL3_ttf.so.0.2.3
```

This checkout may already include compatible shared libraries. If they do not
work on your system, replace them or create symbolic links to compatible SDL3
and SDL3_ttf libraries:

```sh
cd ~/.local/share/SPTK/SDLWrapper
ln -sf /path/to/libSDL3.so.0.2.21 libSDL3.so.0.2.21
ln -sf /path/to/libSDL3_ttf.so.0.2.3 libSDL3_ttf.so.0.2.3
```

On Linux, this can help locate installed SDL libraries:

```sh
ldconfig -p | grep -E 'libSDL3(_ttf)?\.so'
```

If your system only provides differently versioned filenames, either symlink
them to the filenames above or update `SDLWrapper/SDL.php` and
`SDLWrapper/TTF.php` to load the installed names.

## Using SPTK In An App

Define `APP_PATH` and `APP_NAMESPACE`, require the autoloader, then build an
SPTK widget tree and pass it to `SdlApp`:

```php
<?php

define('APP_PATH', __FILE__);
define('APP_NAMESPACE', 'MYAPP');

require_once __DIR__ . '/SPTK/Autoload.php';

use SPTK\Runtime\SdlApp;
use SPTK\Runtime\SdlWindowOptions;
use SPTK\Widgets\Label;

$app = new SdlApp();
$app->addWindow(new Label('hello', 'Hello from SPTK'), new SdlWindowOptions(
  title: 'My SPTK App',
  columns: 80,
  rows: 24
));
$app->run();
```

App classes are loaded from the directory containing `APP_PATH`. Toolkit
classes are loaded from the linked or copied `SPTK/` directory.

## Current Scope

- Headless grid buffer with cells, colors, flags, clipping, blitting, and dirty
  regions.
- Immutable RGBA colors and configurable themes.
- Pixel-first element tree with typed `Len` and `Place` layout values.
- Ordered render targets for cells, text, clipping, borders, surfaces, images,
  textures, and pixel text.
- Input events, focus management, timers, clipboard integration, and
  multi-window SDL runtime.
- Widgets for docks, flows, labels, buttons, inputs, text editing, text blocks,
  status bars, menus, popups, dialogs, lists, tables, selectors, dates, colors,
  images, and graphics surfaces.

XML/XSS compatibility from old SPTK is not part of the current toolkit.

See `Docs/elements.md` for element layout rules and API discipline.

## Examples And Tests

Run the full lightweight test suite:

```sh
php Tests/run.php
```

Run the interactive widget example app:

```sh
php Examples/demo.php
```

The example app requires a working graphical environment and SDL setup.

## Updating

Update the shared SPTK installation from its directory:

```sh
cd ~/.local/share/SPTK
git pull
```

All applications linked to that directory will use the updated version.

## License

SPTK is released into the public domain under the [Unlicense](UNLICENSE).
