# SPTK

SPTK (SDL-based PHP Toolkit) is a lightweight graphical user interface toolkit
written in PHP. It uses SDL3 and SDL3_ttf through PHP FFI and provides XML
layouts, XSS stylesheets, windows, menus, text editors, lists, inputs, images,
and other reusable interface elements.

## Requirements

SPTK requires the SDL3 and SDL3_ttf shared libraries. They may be installed by
your operating system or built from source; they are not included with SPTK.

Install PHP 8.0 or newer with the following CLI extensions enabled:

- FFI
- PCNTL
- Mbstring
- XML and DOM

SDL3_ttf also requires the FreeType and HarfBuzz runtime libraries. Install
these dependencies with your operating system's package manager.

Check the PHP installation with:

```sh
php --version
php -m | grep -E '^(dom|FFI|mbstring|pcntl|xml|xmlreader)$'
```

## Installation

SPTK does not use Composer. Clone the repository to a permanent location, for
example:

```sh
mkdir -p ~/.local/share
git clone https://github.com/madocorp/SPTK.git ~/.local/share/SPTK
```

The resulting directory, `~/.local/share/SPTK` in this example, is the **SPTK
directory path** requested by applications that use the toolkit.

### Configure SDL

SPTK loads these two files from its `SDLWrapper` directory:

```text
SDLWrapper/libSDL3.so
SDLWrapper/libSDL3_ttf.so
```

If SDL3 and SDL3_ttf are installed on your system, create symbolic links using
their actual library paths:

```sh
cd ~/.local/share/SPTK/SDLWrapper
ln -s /path/to/libSDL3.so libSDL3.so
ln -s /path/to/libSDL3_ttf.so libSDL3_ttf.so
```

On Linux, `ldconfig -p | grep -E 'libSDL3(_ttf)?\.so'` can help locate the
installed libraries. The targets may have versioned names such as
`libSDL3.so.0`.

If suitable system libraries are not available, build SDL3 and SDL3_ttf from
source by following [`SDLWrapper/sdl_help.txt`](SDLWrapper/sdl_help.txt), then
copy or link the resulting shared libraries using the names shown above.

## Using SPTK Applications

Applications such as MaDirector, MaDemonstrator, MADB, and MaDventure load the
toolkit from a directory named `SPTK` inside the application's installation
directory. Create a symbolic link using the SPTK directory path from above:

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

Use an absolute path when the application and SPTK are installed in unrelated
locations. The link name must be exactly `SPTK`.

## Updating

Update the shared SPTK installation from its directory:

```sh
cd ~/.local/share/SPTK
git pull
```

All applications linked to that directory will use the updated version.

## License

SPTK is released into the public domain under the [Unlicense](UNLICENSE).
