<?php

namespace SPTK\Widgets\Table;

/** Holds inline rows or a bounded cache of indexed TSV rows. */
final class TableData {

  public const CHUNK_SIZE = 256;

  private array $header = [];
  private array $rows = [];
  private array $sampleRows = [];
  private array $offsets = [];
  private array $chunks = [];
  private ?string $file = null;
  private int $rowCount = 0;
  private int $columnCount = 0;

  /** Replace all records with normalized string or null fields. */
  public function setRows(array $header, array $rows): void {
    $this->file = null;
    $this->offsets = [];
    $this->chunks = [];
    $this->header = array_map($this->field(...), array_values($header));
    $this->rows = [];
    foreach ($rows as $row) {
      $this->rows[] = array_map($this->field(...), array_values($row));
    }
    $this->sampleRows = $this->rows;
    $this->rowCount = count($this->rows);
    $this->columnCount = count($this->header);
    foreach ($this->rows as $row) {
      $this->columnCount = max($this->columnCount, count($row));
    }
  }

  /** Index TSV chunk positions and retain only the first chunk for sizing. */
  public function loadFile(string $path): void {
    $handle = @fopen($path, 'rb');
    if ($handle === false) {
      throw new \RuntimeException("Cannot open Table file: {$path}");
    }
    $first = fgets($handle);
    $header = $first === false ? [] : $this->parseLine(rtrim($first, "\r\n"));
    $offsets = [];
    $sample = [];
    $count = 0;
    $columns = count($header);
    while (($offset = ftell($handle)) !== false && ($line = fgets($handle)) !== false) {
      if ($count % self::CHUNK_SIZE === 0) {
        $offsets[$count] = $offset;
      }
      $columns = max($columns, substr_count($line, "\t") + 1);
      if ($count < self::CHUNK_SIZE) {
        $sample[] = $this->parseLine(rtrim($line, "\r\n"));
      }
      $count++;
    }
    fclose($handle);
    $this->file = $path;
    $this->header = $header;
    $this->rows = [];
    $this->sampleRows = $sample;
    $this->offsets = $offsets;
    $this->chunks = $count > 0 ? [0 => $sample] : [];
    $this->rowCount = $count;
    $this->columnCount = $columns;
  }

  /** Return the header fields. */
  public function header(): array {
    return $this->header;
  }

  /** Return a row or false for an invalid index. */
  public function row(int $index): array|false {
    if ($index < 0 || $index >= $this->rowCount) {
      return false;
    }
    if ($this->file === null) {
      return $this->rows[$index];
    }
    $start = intdiv($index, self::CHUNK_SIZE) * self::CHUNK_SIZE;
    if (!isset($this->chunks[$start])) {
      $this->loadChunk($start);
    }
    return $this->chunks[$start][$index - $start] ?? false;
  }

  /** Return the number of data rows. */
  public function count(): int {
    return $this->rowCount;
  }

  /** Return the largest header or row field count. */
  public function columns(): int {
    return $this->columnCount;
  }

  /** Return in-memory rows or the initial TSV chunk for column measurement. */
  public function measurementRows(): array {
    return $this->sampleRows;
  }

  /** Return the number of data rows currently held in TSV chunk cache. */
  public function cachedRowCount(): int {
    return array_sum(array_map('count', $this->chunks));
  }

  /** Seek directly to one indexed chunk and keep at most two chunks. */
  private function loadChunk(int $start): void {
    $handle = @fopen($this->file, 'rb');
    if ($handle === false) {
      throw new \RuntimeException("Cannot reopen Table file: {$this->file}");
    }
    if (fseek($handle, $this->offsets[$start]) !== 0) {
      fclose($handle);
      throw new \RuntimeException("Cannot seek Table file: {$this->file}");
    }
    $rows = [];
    while (count($rows) < self::CHUNK_SIZE && ($line = fgets($handle)) !== false) {
      $rows[] = $this->parseLine(rtrim($line, "\r\n"));
    }
    fclose($handle);
    if (count($this->chunks) >= 2) {
      unset($this->chunks[array_key_first($this->chunks)]);
    }
    $this->chunks[$start] = $rows;
  }

  /** Convert one supplied field to the table's value type. */
  private function field(mixed $value): ?string {
    return $value === null ? null : (string)$value;
  }

  /** Split a TSV line and decode tabs, line breaks, slashes, and null fields. */
  private function parseLine(string $line): array {
    $fields = [];
    $field = '';
    $escaped = false;
    $null = false;
    for ($i = 0; $i < strlen($line); $i++) {
      $char = $line[$i];
      if ($escaped) {
        if ($char === 'N' && $field === '' && ($i + 1 === strlen($line) || $line[$i + 1] === "\t")) {
          $null = true;
        } else {
          $field .= match ($char) {
            't' => "\t", 'n' => "\n", 'r' => "\r", '\\' => '\\', default => "\\{$char}",
          };
        }
        $escaped = false;
      } else if ($char === '\\') {
        $escaped = true;
      } else if ($char === "\t") {
        $fields[] = $null ? null : $field;
        $field = '';
        $null = false;
      } else {
        $field .= $char;
      }
    }
    if ($escaped) {
      $field .= '\\';
    }
    $fields[] = $null ? null : $field;
    return $fields;
  }

}
