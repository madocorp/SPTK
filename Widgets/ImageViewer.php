<?php

namespace SPTK\Widgets;

use SPTK\Core\ImageRenderTarget;
use SPTK\Core\InputAction;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;
use SPTK\Core\Scrollbar;
use SPTK\Core\SurfaceRenderTarget;

/**
 * Focusable bitmap image viewer with pan and zoom controls.
 */
class ImageViewer extends \SPTK\Core\Element {

  protected ?string $source = null;
  protected ?\GdImage $image = null;
  protected int $imageWidth = 0;
  protected int $imageHeight = 0;
  protected float $zoom = 1.0;
  protected float $offsetX = 0.0;
  protected float $offsetY = 0.0;
  protected int $viewerPixelWidth = 1;
  protected int $viewerPixelHeight = 1;
  protected ?string $error = null;
  protected string|int|\SPTK\Core\Color $borderColor = '#00aa00';
  protected string|int|\SPTK\Core\Color $backgroundColor = '#000000';
  protected string|int|\SPTK\Core\Color $scrollbarColor = '#aa00ff';

  public function __construct(string $name = '', ?string $source = null) {
    parent::__construct($name);
    $this->focusable = true;
    $this->setPreferredRows(16);
    $this->setPreferredColumns(64);
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

  public function zoom(): float {
    return $this->zoom;
  }

  public function offset(): array {
    return [$this->offsetX, $this->offsetY];
  }

  public function handle(InputEvent $event): bool {
    if ($event->type === 'text') {
      if ($event->text === '+' || $event->text === '=') {
        $this->zoomBy(1.25);
        return true;
      }
      if ($event->text === '-') {
        $this->zoomBy(0.8);
        return true;
      }
      return false;
    }
    if ($event->type !== 'key') {
      return false;
    }
    $key = InputAction::normalizedKey($event->key);
    if ($key === '+' || $key === '=') {
      $this->zoomBy(1.25);
      return true;
    }
    if ($key === '-') {
      $this->zoomBy(0.8);
      return true;
    }
    if (InputAction::left($event, 'image-viewer')) {
      $this->pan(-$this->viewerSourceWidth() / 2, 0);
      return true;
    }
    if (InputAction::right($event, 'image-viewer')) {
      $this->pan($this->viewerSourceWidth() / 2, 0);
      return true;
    }
    if (InputAction::up($event, 'image-viewer')) {
      $this->pan(0, -$this->viewerSourceHeight() / 2);
      return true;
    }
    if (InputAction::down($event, 'image-viewer')) {
      $this->pan(0, $this->viewerSourceHeight() / 2);
      return true;
    }
    if (InputAction::home($event, 'image-viewer')) {
      $this->offsetX = 0.0;
      $this->clampOffset();
      $this->invalidateRender();
      return true;
    }
    if (InputAction::end($event, 'image-viewer')) {
      $this->offsetX = $this->maxOffsetX();
      $this->clampOffset();
      $this->invalidateRender();
      return true;
    }
    if (InputAction::pageUp($event, 'image-viewer')) {
      $this->offsetY = 0.0;
      $this->clampOffset();
      $this->invalidateRender();
      return true;
    }
    if (InputAction::pageDown($event, 'image-viewer')) {
      $this->offsetY = $this->maxOffsetY();
      $this->clampOffset();
      $this->invalidateRender();
      return true;
    }
    return false;
  }

  protected function paint(RenderTarget $target): void {
    $target->fill($this->frame, ' ', $this->theme->fg, $this->backgroundColor);
    if ($this->frame->width <= 0 || $this->frame->height <= 0) {
      return;
    }
    $target->drawRect($this->frame, $this->borderColor, 1);
    if (!$this->hasImage()) {
      if ($this->error !== null && $this->frame->width > 2 && $this->frame->height > 2) {
        $target->write($this->frame->x + 1, $this->frame->y + 1, mb_substr($this->error, 0, $this->frame->width - 2), $this->theme->fg, $this->backgroundColor);
      }
      return;
    }
    if (!$target instanceof ImageRenderTarget) {
      return;
    }
    $inner = $this->imagePixelRect($target)->inset(1, 1);
    if ($inner->width <= 0 || $inner->height <= 0) {
      return;
    }
    $this->viewerPixelWidth = $inner->width;
    $this->viewerPixelHeight = $inner->height;
    $this->clampOffset($inner);
    $source = $this->sourceRect($inner);
    if ($source->width <= 0 || $source->height <= 0) {
      return;
    }
    $targetWidth = min($inner->width, max(1, (int)round($source->width * $this->zoom)));
    $targetHeight = min($inner->height, max(1, (int)round($source->height * $this->zoom)));
    $targetRect = new Rect(
      $inner->x + max(0, intdiv($inner->width - $targetWidth, 2)),
      $inner->y + max(0, intdiv($inner->height - $targetHeight, 2)),
      $targetWidth,
      $targetHeight
    );
    $this->drawResampledImage($target, $source, $targetRect);
    $this->paintScrollbars($target, $inner);
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

  protected function sourceRect(Rect $inner): Rect {
    $x = (int)floor($this->offsetX);
    $y = (int)floor($this->offsetY);
    $width = min($this->imageWidth - $x, (int)ceil($inner->width / $this->zoom));
    $height = min($this->imageHeight - $y, (int)ceil($inner->height / $this->zoom));
    return new Rect($x, $y, max(0, $width), max(0, $height));
  }

  protected function drawResampledImage(ImageRenderTarget $target, Rect $source, Rect $targetRect): void {
    if ($source->width === $targetRect->width && $source->height === $targetRect->height) {
      $target->drawImagePixels($this->image, $source, $targetRect);
      return;
    }
    $scaled = imagecreatetruecolor($targetRect->width, $targetRect->height);
    if (!$scaled instanceof \GdImage) {
      $target->drawImagePixels($this->image, $source, $targetRect);
      return;
    }
    imagealphablending($scaled, false);
    imagesavealpha($scaled, true);
    imagefilledrectangle($scaled, 0, 0, $targetRect->width, $targetRect->height, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
    if (function_exists('imagesetinterpolation') && defined('IMG_BICUBIC_FIXED')) {
      imagesetinterpolation($this->image, IMG_BICUBIC_FIXED);
      imagesetinterpolation($scaled, IMG_BICUBIC_FIXED);
    }
    imagecopyresampled(
      $scaled,
      $this->image,
      0,
      0,
      $source->x,
      $source->y,
      $targetRect->width,
      $targetRect->height,
      $source->width,
      $source->height
    );
    $target->drawImagePixels($scaled, new Rect(0, 0, $targetRect->width, $targetRect->height), $targetRect);
    imagedestroy($scaled);
  }

  protected function paintScrollbars(RenderTarget $target, Rect $inner): void {
    $visibleWidth = min($this->imageWidth, max(1, (int)ceil($inner->width / $this->zoom)));
    if ($this->imageWidth > $visibleWidth) {
      Scrollbar::paintBar(
        $target,
        new Rect($this->frame->x, $this->frame->bottom() - 1, $this->frame->width, 1),
        (int)floor($this->offsetX),
        $visibleWidth,
        $this->imageWidth,
        'horizontal',
        $this->scrollbarColor
      );
    }
    $visibleHeight = min($this->imageHeight, max(1, (int)ceil($inner->height / $this->zoom)));
    if ($this->imageHeight > $visibleHeight) {
      Scrollbar::paintBar(
        $target,
        new Rect($this->frame->right() - 1, $this->frame->y, 1, $this->frame->height),
        (int)floor($this->offsetY),
        $visibleHeight,
        $this->imageHeight,
        'vertical',
        $this->scrollbarColor
      );
    }
  }

  protected function zoomBy(float $factor): void {
    $this->zoom = max(0.1, min(16.0, $this->zoom * $factor));
    $this->clampOffset();
    $this->invalidateRender();
  }

  protected function pan(float $x, float $y): void {
    $this->offsetX += $x;
    $this->offsetY += $y;
    $this->clampOffset();
    $this->invalidateRender();
  }

  protected function viewerSourceWidth(?Rect $inner = null): float {
    $inner ??= new Rect(0, 0, $this->viewerPixelWidth, $this->viewerPixelHeight);
    return $inner->width / max(0.1, $this->zoom);
  }

  protected function viewerSourceHeight(?Rect $inner = null): float {
    $inner ??= new Rect(0, 0, $this->viewerPixelWidth, $this->viewerPixelHeight);
    return $inner->height / max(0.1, $this->zoom);
  }

  protected function maxOffsetX(?Rect $inner = null): float {
    return max(0.0, $this->imageWidth - $this->viewerSourceWidth($inner));
  }

  protected function maxOffsetY(?Rect $inner = null): float {
    return max(0.0, $this->imageHeight - $this->viewerSourceHeight($inner));
  }

  protected function clampOffset(?Rect $inner = null): void {
    $this->offsetX = max(0.0, min($this->offsetX, $this->maxOffsetX($inner)));
    $this->offsetY = max(0.0, min($this->offsetY, $this->maxOffsetY($inner)));
  }

  protected function setImage(\GdImage $image): void {
    imagepalettetotruecolor($image);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    $this->image = $image;
    $this->imageWidth = imagesx($image);
    $this->imageHeight = imagesy($image);
    $this->zoom = 1.0;
    $this->offsetX = 0.0;
    $this->offsetY = 0.0;
    $this->error = null;
    $this->invalidateRender();
  }

  protected function clearImage(string $error): void {
    $this->image = null;
    $this->imageWidth = 0;
    $this->imageHeight = 0;
    $this->zoom = 1.0;
    $this->offsetX = 0.0;
    $this->offsetY = 0.0;
    $this->error = $error;
    $this->invalidateRender();
  }

  protected function hasImage(): bool {
    return $this->image instanceof \GdImage && $this->imageWidth > 0 && $this->imageHeight > 0;
  }

  protected function resolvePath(string $path): string {
    if (str_starts_with($path, '/')) {
      return $path;
    }
    return getcwd() . '/' . $path;
  }

}
