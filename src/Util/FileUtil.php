<?php

namespace Civi\Coworker\Util;

class FileUtil {

  /**
   * Create a file.
   * Make dir if needed.
   * Overwrite if existing.
   *
   * @param string $file
   * @param string $content
   * @return string
   *   File name
   */
  public static function put(string $file, string $content): string {
    if (!is_dir(dirname($file))) {
      mkdir(dirname($file), 0777, TRUE);
    }
    file_put_contents($file, $content);
    return $file;
  }

  /**
   * Get the path to a file in the "state" (local working dir).
   *
   * @param string|null $subpath
   * @return string
   */
  public static function getStatePath(?string $subpath): string {
    $stateHome = getenv('XDG_STATE_HOME') ?: (getenv('HOME') . '/.local/state');
    $path = "$stateHome/coworker";
    if ($subpath) {
      $path .= "/" . ltrim($subpath, '/');
    }
    return $path;
  }

}
