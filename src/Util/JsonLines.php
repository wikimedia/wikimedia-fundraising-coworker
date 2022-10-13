<?php

namespace Civi\Coworker\Util;

class JsonLines {

  /**
   * Parse a file with many independent lines of JSON.
   *
   * @param string $file
   *   Path to a file.
   * @return array
   */
  public static function parseFile(string $file): array {
    $content = rtrim(file_get_contents($file), "\r\n");
    $lines = explode("\n", $content);
    return ($content === '') ? [] : array_map(
      function ($line) {
        return \json_decode($line, 1);
      },
      $lines
    );
  }

}
