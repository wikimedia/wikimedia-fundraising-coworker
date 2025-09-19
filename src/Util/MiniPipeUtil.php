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
      $requireMiniPipe = implode("\n", [
        '<' . "?php",
        sprintf('Phar::loadPhar(%s,"coworker.phar");', var_export(COWORKER_PHAR, 1)),
        'require_once "phar://coworker.phar/bin/minipipe";',
      ]);
      $stub = static::findCreateStub($requireMiniPipe);
      $result = sprintf('php %s', ProcessUtil::lazyEscape($stub));
    }
    else {
      $miniPipe = dirname(COWORKER_MAIN) . DIRECTORY_SEPARATOR . 'minipipe';
      if (!file_exists($miniPipe)) {
        throw new \RuntimeException("Cannot use builtin pipe adapter. File note found: $miniPipe");
      }
      $result = sprintf('php %s', ProcessUtil::lazyEscape($miniPipe));
    }

    if ($negotiationFlags !== NULL) {
      $result .= ' ' . ProcessUtil::lazyEscape($negotiationFlags);
    }
    return $result;
  }

  protected static function findCreateStub(string $body): string {
    $checksum = hash('sha256', $body);
    $file = FileUtil::getStatePath("/minipipe/minipipe-{$checksum}.php");
    FileUtil::put($file, $body);
    return $file;
  }

}
