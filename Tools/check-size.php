<?php

// One class per file. Count the entire file, including comments and blank lines,
// so moving code outside a class or compressing formatting cannot hide its size.
$root = $argv[1] ?? dirname(__DIR__);
if (!is_dir($root)) {
  fwrite(STDERR, "Directory not found: {$root}\n");
  exit(1);
}
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$failed = false;
$count = 0;
$largest = 0;
foreach ($files as $file) {
  if (!$file->isFile() || $file->getExtension() !== 'php') {
    continue;
  }
  $source = file_get_contents($file->getPathname());
  $tokens = token_get_all($source);
  $hasClass = false;
  $previous = null;
  foreach ($tokens as $token) {
    if (is_array($token)) {
      if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
        continue;
      }
      if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true) && $previous !== T_DOUBLE_COLON) {
        $hasClass = true;
      }
      $previous = $token[0];
    } else {
      $previous = $token;
    }
  }
  if (!$hasClass) {
    continue;
  }
  $lines = substr_count($source, "\n") + (str_ends_with($source, "\n") ? 0 : 1);
  $count++;
  $largest = max($largest, $lines);
  if ($lines > 300) {
    $level = $lines > 500 ? 'ERROR' : 'REVIEW';
    echo "{$level}: {$file->getPathname()} has {$lines} physical lines\n";
  }
  $failed = $failed || $lines > 500;
}
echo "Checked {$count} class-bearing PHP files; largest: {$largest} lines\n";
exit($failed ? 1 : 0);
