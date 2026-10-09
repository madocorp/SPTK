# SPTK

SPTK is a PHP 8.2+ user interface toolkit. It uses SDL3 and SDL3_ttf through
PHP FFI for windows, pixel rendering, and text. Applications describe screens
with XML layouts and can use the widgets in `Widgets/`.

## Requirements

- PHP CLI 8.2 or newer with FFI, XMLReader, mbstring, and PCNTL enabled.
  GD is needed for graphs and non-PNG images.
- Compatible SDL3 and SDL3_ttf shared libraries. SDL3_ttf also needs FreeType
  and HarfBuzz. PNG images require `libpng16.so.16`.
- A graphical desktop session for interactive applications.

Check the PHP extensions with:

```sh
php --version
php -m | grep -Ei '^(FFI|xmlreader|mbstring|gd|pcntl)$'
```

Install missing extensions and native libraries with your system's package
manager or build SDL from its upstream sources. Package names vary by system.

## Installation

SPTK does not use Composer. Clone it to a permanent location:

```sh
mkdir -p "$HOME/.local/share"
git clone https://github.com/madocorp/SPTK.git "$HOME/.local/share/SPTK"
```

SDL source trees and shared libraries are not included in this repository.
The SDL loader expects these files in `SDLWrapper/`:

```text
libSDL3.so.0.2.21
libSDL3_ttf.so.0.2.3
```

Install compatible SDL3 and SDL3_ttf libraries, then place them there or
create symlinks to their absolute paths. On Linux, `ldconfig -p` can help
locate installed libraries. The PHP bindings and FFI headers in `SDLWrapper/`
are part of SPTK and remain in the repository.

Applications load the toolkit from a directory or symlink named `SPTK`
beside their entry point. For example:

```sh
ln -s "$HOME/.local/share/SPTK" /path/to/application/SPTK
```

Use an absolute target when the application and SPTK live in different
directories. Each application defines `APP_DIR` as its own directory. To load
application classes, define `APP_NAMESPACE` as the root namespace before
requiring `SPTK/App.php`. For example, `define('APP_NAMESPACE', 'MYAPP')` maps
`MYAPP\Screen\Main` to `APP_DIR/Screen/Main.php`. Requiring `SPTK/App.php`
registers one loader for SPTK and the optional application namespace; no
separate `spl_autoload_register()` call is needed.

## Demo and tests

From the SPTK checkout, run the interactive demo with:

```sh
php Demo/demo.php
```

On the Text screen, keys 1–6 demonstrate modal information, warning, error,
confirmation, continuous guidance, and a timed background message in the
StatusBar. Press H for the selected tile's hint.

Individual tests can be run without the demo window, for example:

```sh
php Tests/StyledText.php
php Tests/PixelLayout.php
```

See [classes](Docs/classes.md) and [layout](Docs/layout.md) for the current
toolkit structure and layout rules.

## License

SPTK is released under the [Unlicense](UNLICENSE).
