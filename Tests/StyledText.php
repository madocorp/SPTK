<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\{RasterImage, Style};
use SPTK\Widgets\StyledText\{Format, Fonts, Lines, Parser, StyledText};

/** Check a rich text rendering behavior. */
function expectStyled(mixed $actual, mixed $expected, string $name): void {
  if ($actual !== $expected) {
    throw new RuntimeException($name . ': ' . var_export($actual, true));
  }
}

/** Read one native-endian RGBA raster pixel. */
function styledPixel(RasterImage $image, int $x, int $y): int {
  return unpack('L', substr($image->pixels, ($y * $image->width + $x) * 4, 4))[1];
}

/** Find the horizontal extent of nontransparent text pixels. */
function styledInk(RasterImage $image): array {
  $left = $image->width;
  $right = -1;
  for ($y = 0; $y < $image->height; $y++) {
    for ($x = 0; $x < $image->width; $x++) {
      if ((styledPixel($image, $x, $y) & 255) > 0) {
        $left = min($left, $x);
        $right = max($right, $x);
      }
    }
  }
  return [$left, $right];
}

$text = new StyledText('Hello', ['fontFamily' => 'sans-serif', 'fontSize' => 30]);
$image = $text->raster(240, 90);
expectStyled([$image->width, $image->height], [240, 90], 'exact tile dimensions');
expectStyled($text->raster(240, 90) === $image, true, 'cached raster');
$text->setContent('Hello', $text->options());
expectStyled($text->raster(240, 90) === $image, true, 'unchanged setter retains raster');
$oldRuns = $text->runs();
foreach ([['fontSize' => -1], ['fontSize' => 'wrong'], ['fontSize' => []], ['fontStyle' => 'wrong'], ['fontFamily' => []], ['padding' => ['wrong' => 2]], ['textAlign' => 'diagonal'], ['color' => 'bad'], ['other' => true]] as $invalid) {
  try {
    $text->setContent('Changed', $invalid);
    throw new RuntimeException('Invalid style accepted.');
  } catch (InvalidArgumentException $error) {
    expectStyled($text->runs(), $oldRuns, 'failed style update retains text');
  }
}
$long = new StyledText('One two three four five six seven eight nine ten', ['fontSize' => 26]);
expectStyled($long->contentHeight(100) > $long->contentHeight(500), true, 'word wrapping grows height');
$unwrapped = new StyledText('One two three four five six seven eight nine ten', ['fontSize' => 26, 'wrap' => false]);
expectStyled($unwrapped->contentHeight(100), $unwrapped->contentHeight(500), 'unwrapped text stays on one line');
$breaks = new StyledText([['text' => 'one'], ['type' => 'br'], ['text' => 'two\nthree']]);
expectStyled($breaks->contentHeight(400) > $text->contentHeight(400), true, 'explicit break increases height');
$styles = array_replace(Format::DEFAULTS, ['fontSize' => 20]);
$lines = (new Lines(new Fonts()))->layout([['text' => 'hello '], ['text' => 'world', 'fontSize' => 40]], $styles, 500, 500, 300);
expectStyled(count($lines), 1, 'mixed font sizes share a line');
$small = (new Lines(new Fonts()))->layout([['text' => 'hello']], $styles, 500, 500, 300);
expectStyled($lines[0]['ascent'] > $small[0]['ascent'], true, 'large run grows common ascent');
$unicode = (new Lines(new Fonts()))->layout([['text' => "ábc👩‍💻defghijklmnopqrstuvwxyz"]], $styles, 35, 100, 100);
$joined = '';
foreach ($unicode as $line) {
  foreach ($line['segments'] as $segment) {
    $joined .= $segment['text'];
  }
}
expectStyled($joined, "ábc👩‍💻defghijklmnopqrstuvwxyz", 'oversized words retain graphemes');
$left = styledInk((new StyledText('Hi', ['fontSize' => 20]))->raster(200, 50));
$center = styledInk((new StyledText('Hi', ['fontSize' => 20, 'textAlign' => 'center']))->raster(200, 50));
$right = styledInk((new StyledText('Hi', ['fontSize' => 20, 'textAlign' => 'right']))->raster(200, 50));
expectStyled($left[0] < $center[0] && $center[0] < $right[0], true, 'alignment moves rendered ink');
$decorated = new StyledText('Text', ['background' => '#123456', 'borderColor' => '#ff0000', 'borderWidth' => 3, 'padding' => 10]);
$decoratedImage = $decorated->raster(180, 80);
expectStyled(styledPixel($decoratedImage, 0, 0), 0xff0000ff, 'opaque border');
expectStyled(styledPixel($decoratedImage, 5, 5), 0x123456ff, 'padded background');
$inline = new StyledText([['text' => 'A', 'background' => '#00ff00', 'color' => '#ff0000']]);
expectStyled(styledPixel($inline->raster(100, 60), 0, 0), 0x00ff00ff, 'inline run background');
expectStyled(Format::color('#abc'), [170, 187, 204, 0], 'short RGB');
expectStyled(Format::color('transparent'), [0, 0, 0, 127], 'transparent color');
expectStyled($text->raster(1, 1)->width, 1, 'tiny tile clips safely');
$reader = new XMLReader();
$reader->XML('<StyledText fontSize="24" textAlign="center">First <Run bold="true" color="#ff0000">bold</Run><Br/>next<Style><Background>#112233</Background></Style></StyledText>');
$reader->read();
$parser = new Parser();
$parser->validateAttributes($reader, []);
$definition = $parser->parse($reader, new Style());
expectStyled($definition->widget->runs(), [['text' => 'First '], ['text' => 'bold', 'color' => '#ff0000', 'bold' => true], ['type' => 'br'], ['text' => 'next']], 'XML runs preserve order and inline styles');
expectStyled($definition->widget->background()->r, 17, 'XML background inheritance');
expectStyled($definition->widget->canActivate(), false, 'read-only widget');
echo "StyledText tests passed\n";
