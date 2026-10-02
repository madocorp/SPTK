<?php

namespace SPTK\Widgets\StyledText;

/** Wraps styled runs into lines with a common baseline for mixed font sizes. */
final class Lines {

  private array $lines = [];
  private array $line;

  /** Share the font metrics used by layout and raster painting. */
  public function __construct(private Fonts $fonts) {
  }

  /** Wrap tokens and oversized graphemes within the supplied content width. */
  public function layout(array $runs, array $style, int $width, int $referenceWidth, int $referenceHeight): array {
    $this->lines = [];
    $default = $this->fonts->face($style, $referenceWidth, $referenceHeight);
    $this->line = $this->emptyLine($default);
    foreach ($runs as $run) {
      if (($run['type'] ?? '') === 'br') {
        $this->finish($default);
        continue;
      }
      $runStyle = array_replace($style, $run);
      $face = $this->fonts->face($runStyle, $referenceWidth, $referenceHeight);
      $tokens = preg_split('/(\n|[^\S\n]+)/u', $run['text'], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
      foreach ($tokens as $token) {
        if ($token === "\n") {
          $this->finish($default);
        } else {
          $this->token($token, $runStyle, $face, max(1, $width), $default);
        }
      }
    }
    $this->lines[] = $this->line;
    return $this->lines;
  }

  /** Wrap a token, splitting long words only at grapheme boundaries. */
  private function token(string $token, array $style, array $face, int $width, array $default): void {
    $metrics = $this->fonts->measure($token, $face);
    if ($style['wrap'] && $this->line['width'] > 0 && $this->line['width'] + $metrics[0] > $width) {
      $this->finish($default);
      if (trim($token) === '') {
        return;
      }
    }
    if ($style['wrap'] && $metrics[0] > $width && trim($token) !== '') {
      preg_match_all('/\X/u', $token, $graphemes);
      foreach ($graphemes[0] as $grapheme) {
        $measure = $this->fonts->measure($grapheme, $face);
        if ($this->line['width'] > 0 && $this->line['width'] + $measure[0] > $width) {
          $this->finish($default);
        }
        $this->append($grapheme, $style, $face, $measure);
      }
      return;
    }
    $this->append($token, $style, $face, $metrics);
  }

  /** Append a measured segment and grow the shared ascent and descent. */
  private function append(string $text, array $style, array $face, array $metrics): void {
    $this->line['segments'][] = ['text' => $text, 'style' => $style, 'face' => $face, 'width' => $metrics[0]];
    $this->line['width'] += $metrics[0];
    [$ascent, $descent] = $this->fonts->lineMetrics($face);
    $this->line['ascent'] = max($this->line['ascent'], $ascent);
    $this->line['descent'] = max($this->line['descent'], $descent);
  }

  /** Flush a line while preserving explicit empty lines. */
  private function finish(array $face): void {
    $this->lines[] = $this->line;
    $this->line = $this->emptyLine($face);
  }

  /** Initialize line metrics including descenders even for an empty line. */
  private function emptyLine(array $face): array {
    [$ascent, $descent] = $this->fonts->lineMetrics($face);
    return ['segments' => [], 'width' => 0, 'ascent' => $ascent, 'descent' => $descent];
  }

}
