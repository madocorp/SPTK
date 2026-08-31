<?php

namespace SPTK\Widgets;

use SPTK\Core\ImageRenderTarget;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;
use SPTK\Core\SurfaceRenderTarget;

/**
 * Displays a GD-supported bitmap image inside normal widget layouts.
 */
class ImageView extends \SPTK\Core\Element {

  protected ?string $source = null;
  protected ?\GdImage $image = null;
  protected int $imageWidth = 0;
  protected int $imageHeight = 0;
  protected int $sourceX = 0;
  protected int $sourceY = 0;
  protected ?int $sourceWidth = null;
  protected ?int $sourceHeight = null;
  protected ?int $cellWidth = null;
  protected ?int $cellHeight = null;
  protected ?int $pixelWidth = null;
  protected ?int $pixelHeight = null;
  protected string $fit = 'contain';
  protected ?string $error = null;

  public function __construct(string $name = '', ?string $source = null) {
    parent::__construct($name);
    $this->setPreferredRows(1);
    if ($source !== null) {
      $this->setSource($source);
    }
  }

  public function setSource(string $path): static {
    $this->source = $this->resolvePath($path);
    $this->error = null;
    $data = is_file($this->source) ? file_get_contents($this->source) : false;
    if ($data === false) {
      $this->clearImage("Image not found: {$path}");
      return $this;
    }
    return $this->setBytes($data);
  }

  public function source(): ?string {
    return $this->source;
  }

  public function setBytes(string $data): static {
    if (!function_exists('imagecreatefromstring')) {
      $this->clearImage('GD image support is not available.');
      return $this;
    }
    $image = @imagecreatefromstring($data);
    if (!$image instanceof \GdImage) {
      $this->clearImage('Unsupported or invalid image data.');
      return $this;
    }
    $this->setImage($image);
    return $this;
  }

  public function setBase64(string $data): static {
    if (str_starts_with($data, 'data:')) {
      $separator = strpos($data, ',');
      if ($separator === false) {
        $this->clearImage('Invalid image data URI.');
        return $this;
      }
      $data = substr($data, $separator + 1);
    }
    $decoded = base64_decode($data, true);
    if ($decoded === false) {
      $this->clearImage('Invalid base64 image data.');
      return $this;
    }
    return $this->setBytes($decoded);
  }

  public function setSourceRect(int $x, int $y, ?int $width = null, ?int $height = null): static {
    $this->sourceX = max(0, $x);
    $this->sourceY = max(0, $y);
    $this->sourceWidth = $width === null ? null : max(1, $width);
    $this->sourceHeight = $height === null ? null : max(1, $height);
    $this->updatePreferredSize();
    $this->invalidateRender();
    return $this;
  }

  public function setFit(string $fit): static {
    $this->fit = in_array($fit, ['contain', 'stretch'], true) ? $fit : 'contain';
    $this->invalidateRender();
    return $this;
  }

  public function fit(): string {
    return $this->fit;
  }

  public function setCellSize(?int $columns, ?int $rows = null): static {
    $this->cellWidth = $columns === null ? null : max(1, $columns);
    $this->cellHeight = $rows === null ? null : max(1, $rows);
    $this->pixelWidth = null;
    $this->pixelHeight = null;
    if ($columns !== null && $rows !== null) {
      $this->fit = 'stretch';
    }
    $this->updatePreferredSize();
    $this->invalidateRender();
    return $this;
  }

  public function setPixelSize(?int $width, ?int $height = null): static {
    $this->pixelWidth = $width === null ? null : max(1, $width);
    $this->pixelHeight = $height === null ? null : max(1, $height);
    $this->cellWidth = null;
    $this->cellHeight = null;
    if ($width !== null && $height !== null) {
      $this->fit = 'stretch';
    }
    $this->updatePreferredSize();
    $this->invalidateRender();
    return $this;
  }

  public function imageWidth(): int {
    return $this->imageWidth;
  }

  public function imageHeight(): int {
    return $this->imageHeight;
  }

  public function sourceWidth(): int {
    return $this->sourceWidth ?? max(1, $this->imageWidth - $this->sourceX);
  }

  public function sourceHeight(): int {
    return $this->sourceHeight ?? max(1, $this->imageHeight - $this->sourceY);
  }

  public function preferredRowsForColumns(int $columns): int {
    if ($this->cellHeight !== null) {
      return $this->cellHeight;
    }
    if ($this->cellWidth !== null && $this->hasImage()) {
      return max(1, (int)round($this->cellWidth * $this->sourceHeight() / $this->sourceWidth()));
    }
    return parent::preferredRowsForColumns($columns);
  }

  protected function paint(RenderTarget $target): void {
    $target->fill($this->frame, ' ', $this->theme->fg, $this->theme->bg);
    if (!$this->hasImage()) {
      if ($this->error !== null && $this->frame->width > 0 && $this->frame->height > 0) {
        $target->write($this->frame->x, $this->frame->y, mb_substr($this->error, 0, $this->frame->width), $this->theme->fg, $this->theme->bg);
      }
      return;
    }
    if (!$target instanceof ImageRenderTarget) {
      return;
    }
    $pixelRect = $this->sizedPixelRect($this->imagePixelRect($target));
    $drawRect = $this->fit === 'stretch' ? $pixelRect : $this->containedRect($pixelRect);
    if ($drawRect->width <= 0 || $drawRect->height <= 0) {
      return;
    }
    $target->drawImagePixels($this->image, $this->sourceRect(), $drawRect);
  }

  protected function imagePixelRect(ImageRenderTarget $target): Rect {
    if ($target instanceof SurfaceRenderTarget) {
      $surface = $target->currentSurfacePixelRect();
      $columns = $target->columnsForWidth($surface->width);
      $rows = $target->rowsForHeight($surface->height);
      if ($this->frame->x === 0 && $this->frame->y === 0 && $this->frame->width === $columns && $this->frame->height === $rows) {
        return $surface;
      }
    }
    return $target->pixelRectForCells($this->frame);
  }

  protected function containedRect(Rect $rect): Rect {
    $sourceWidth = $this->sourceWidth();
    $sourceHeight = $this->sourceHeight();
    if ($sourceWidth <= 0 || $sourceHeight <= 0 || $rect->width <= 0 || $rect->height <= 0) {
      return new Rect($rect->x, $rect->y, 0, 0);
    }
    $scale = min($rect->width / $sourceWidth, $rect->height / $sourceHeight);
    $width = max(1, (int)round($sourceWidth * $scale));
    $height = max(1, (int)round($sourceHeight * $scale));
    return new Rect(
      $rect->x + max(0, intdiv($rect->width - $width, 2)),
      $rect->y + max(0, intdiv($rect->height - $height, 2)),
      min($rect->width, $width),
      min($rect->height, $height)
    );
  }

  protected function sizedPixelRect(Rect $rect): Rect {
    if ($this->pixelWidth === null && $this->pixelHeight === null) {
      return $rect;
    }
    $width = $this->pixelWidth;
    $height = $this->pixelHeight;
    if ($width === null && $height !== null) {
      $width = $this->hasImage() ? (int)round($height * $this->sourceWidth() / $this->sourceHeight()) : $rect->width;
    }
    if ($height === null && $width !== null) {
      $height = $this->hasImage() ? (int)round($width * $this->sourceHeight() / $this->sourceWidth()) : $rect->height;
    }
    $width = max(1, $width ?? $rect->width);
    $height = max(1, $height ?? $rect->height);
    return new Rect(
      $rect->x + intdiv($rect->width - $width, 2),
      $rect->y + intdiv($rect->height - $height, 2),
      $width,
      $height
    );
  }

  public function resolvedPixelWidth(): ?int {
    if ($this->pixelWidth !== null) {
      return $this->pixelWidth;
    }
    if ($this->pixelHeight !== null && $this->hasImage()) {
      return max(1, (int)round($this->pixelHeight * $this->sourceWidth() / $this->sourceHeight()));
    }
    return null;
  }

  public function resolvedPixelHeight(): ?int {
    if ($this->pixelHeight !== null) {
      return $this->pixelHeight;
    }
    if ($this->pixelWidth !== null && $this->hasImage()) {
      return max(1, (int)round($this->pixelWidth * $this->sourceHeight() / $this->sourceWidth()));
    }
    return null;
  }

  protected function sourceRect(): Rect {
    return new Rect(
      min($this->sourceX, max(0, $this->imageWidth - 1)),
      min($this->sourceY, max(0, $this->imageHeight - 1)),
      min($this->sourceWidth(), max(1, $this->imageWidth - $this->sourceX)),
      min($this->sourceHeight(), max(1, $this->imageHeight - $this->sourceY))
    );
  }

  protected function setImage(\GdImage $image): void {
    imagepalettetotruecolor($image);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    $this->image = $image;
    $this->imageWidth = imagesx($image);
    $this->imageHeight = imagesy($image);
    $this->error = null;
    $this->updatePreferredSize();
    $this->invalidateRender();
  }

  protected function clearImage(string $error): void {
    $this->image = null;
    $this->imageWidth = 0;
    $this->imageHeight = 0;
    $this->error = $error;
    $this->updatePreferredSize();
    $this->invalidateRender();
  }

  protected function hasImage(): bool {
    return $this->image instanceof \GdImage && $this->imageWidth > 0 && $this->imageHeight > 0;
  }

  protected function updatePreferredSize(): void {
    if ($this->cellWidth !== null && $this->cellHeight !== null) {
      $this->setPreferredColumns($this->cellWidth);
      $this->setPreferredRows($this->cellHeight);
      return;
    }
    if ($this->cellWidth !== null) {
      $this->setPreferredColumns($this->cellWidth);
      $this->setPreferredRows($this->hasImage() ? (int)round($this->cellWidth * $this->sourceHeight() / $this->sourceWidth()) : 1);
      return;
    }
    if ($this->cellHeight !== null) {
      $this->setPreferredRows($this->cellHeight);
      $this->setPreferredColumns($this->hasImage() ? (int)round($this->cellHeight * $this->sourceWidth() / $this->sourceHeight()) : 0);
      return;
    }
    $this->setPreferredColumns(0);
    $this->setPreferredRows(1);
  }

  protected function resolvePath(string $path): string {
    if (str_starts_with($path, '/')) {
      return $path;
    }
    return getcwd() . '/' . $path;
  }

}
