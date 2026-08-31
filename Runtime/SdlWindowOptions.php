<?php

namespace SPTK\Runtime;

/**
 * Defines SDL window startup size, font, and display mode for one root element.
 */
class SdlWindowOptions {

  public function __construct(
    public string|bool $title = false,
    public int $columns = 100,
    public int $rows = 32,
    public string $fontName = 'LiberationMono-Bold',
    public int $fontSize = 16,
    public bool $resizable = true,
    public bool $maximized = false,
    public bool $fullscreen = false,
    public ?int $rowHeight = null
  ) {
  }

}
