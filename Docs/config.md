# Configuration files

`SPTK\Core\AppData` stores per-application files in `~/.<app-name>`: configuration and any other
application data, such as caches, sessions, or saves. Use `AppData::file()` or `AppData::path()` for
non-JSON files. The default app name is
`basename(APP_DIR)`, so the bundled demo uses `~/.Demo`. Pass an explicit app name to keep the same
configuration location when the application's directory is renamed.

The home directory comes from `HOME`, then `USERPROFILE`, then the current working directory. The data
directory is created on demand with mode `0700` on Unix; existing data directories are tightened when accessed. Leading dots are
removed from app names, and `/` and `\` become `-`; an empty normalized name becomes `sptk`.

```php
use SPTK\Core\AppData;

$config = AppData::loadJson('config.json');
$theme = $config['theme'] ?? 'dark';
$config['theme'] = $theme;
if (!AppData::saveJson('config.json', $config)) {
  throw new RuntimeException('Could not save configuration.');
}
```

- `path(?string $appName = null)` returns the data directory and creates it if needed.
- `file(string $name, ?string $appName = null)` returns a path inside that directory.
- `loadJson(string $name, ?string $appName = null)` returns an associative array. Missing, unreadable,
  malformed, or scalar JSON returns `[]`. Existing Unix files are tightened to mode `0600` when read;
  a permissions failure throws `RuntimeException`.
- `saveJson(string $name, array $data, ?string $appName = null)` writes pretty-printed UTF-8 JSON with
  unescaped Unicode and slashes, a trailing newline, and an exclusive write lock. On Unix, saved files
  use mode `0600`, including existing files. It returns `false` if encoding, writing, or setting file
  permissions fails. Directory creation failures throw `RuntimeException`.

For example, `AppData::loadJson('config.json', 'my-app')` reads `~/.my-app/config.json`.
File names should be application-controlled relative paths; nested directories are not created automatically.
Applications can load configuration in their `init` action and save it in their `close` action; see
[events.md](events.md) for lifecycle events.
