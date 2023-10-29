<?php

namespace Civi\Coworker\Util;

class MiniPipeUtil {

  /**
   * Construct a CLI command to invoke `bin/minipipe`.
   * @param string|null $negotiationFlags
   * @return string
   */
  public static function createCommand(?string $negotiationFlags = NULL): string {
    if (defined('COWORKER_PHAR')) {
      $requireMiniPipe = sprintf('Phar::loadPhar(%s,"coworker.phar"); require_once "phar://coworker.phar/bin/minipipe";',
        var_export(COWORKER_PHAR, 1));
      $result = sprintf('php -r %s', escapeshellarg($requireMiniPipe));
    }
    else {
      $miniPipe = dirname(COWORKER_MAIN) . DIRECTORY_SEPARATOR . 'minipipe';
      if (!file_exists($miniPipe)) {
        throw new \RuntimeException("Cannot use builtin pipe adapter. File note found: $miniPipe");
      }
      $requireMiniPipe = sprintf("require_once %s;", var_export($miniPipe, 1));
      $result = sprintf('php -r %s', escapeshellarg($requireMiniPipe));
      // The below would be simpler, but the above is a better facsimile of the PHAR behavior.
      // $result = sprintf('php %s', escapeshellarg($miniPipe));
    }
    if ($negotiationFlags !== NULL) {
      $result .= ' ' . escapeshellarg($negotiationFlags);
    }
    return $result;
  }

}
