<?php

namespace SPTK\Tests;

use SPTK\Core\Clipboard;

return [
  'clipboard set updates primary selection provider when available' => function(): void {
    $provider = new class {
      public string $clipboard = '';
      public string $primary = '';

      public function set(string $text): void {
        $this->clipboard = $text;
      }

      public function setPrimary(string $text): void {
        $this->primary = $text;
      }

      public function get(): string {
        return $this->clipboard;
      }
    };

    Clipboard::setProvider($provider);
    Clipboard::set('copied text');

    assertSame('copied text', $provider->clipboard, 'Clipboard provider should receive copied text.');
    assertSame('copied text', $provider->primary, 'Primary selection provider should receive copied text.');
    assertSame('copied text', Clipboard::get(), 'Clipboard get should continue to read the standard clipboard.');
    Clipboard::setProvider(null);
  },
];
