<?php

namespace Civi\Coworker\Util;

class IdUtil {

  private static $id = [];

  /**
   * Generate the next ID for some series of IDs.
   *
   * @param string $seriesName
   *   Name of the series. Values evolve separately for each series.
   * @param int|NULL $max
   *   Set the upper limit on how the ID series may go. If exceeded, loop back.
   * @return int
   */
  public static function next(string $seriesName = '', ?int $max = NULL): int {
    static::$id[$seriesName] = 1 + (static::$id[$seriesName] ?? 0);
    if ($max !== NULL & static::$id[$seriesName] >= $max) {
      static::$id[$seriesName] = 0;
    }
    return static::$id[$seriesName];
  }

}
