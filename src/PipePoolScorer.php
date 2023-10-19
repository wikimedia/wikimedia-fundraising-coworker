<?php

namespace Civi\Coworker;

/**
 * When it comes time to cleanup connections, we have to decide which connections to keep - and which to drop.
 *
 * To do this, we calculate a score for each connection:
 *
 * - Higher values should be dropped before lower values.
 * - Positive values are droppable. Negative values must be kept.
 */
class PipePoolScorer {

  /**
   * @var \Civi\Coworker\Configuration
   */
  protected $config;

  /**
   * @param \Civi\Coworker\Configuration $config
   */
  public function __construct(Configuration $config) {
    $this->config = $config;
  }

  /**
   * @param \Civi\Coworker\PipeConnection $c
   * @return int
   *   - Higher values should be dropped before lower values.
   *   - Positive values are droppable. Negative values must be kept.
   */
  public function score(PipeConnection $c): int {
    // TODO: Think more about which scorer to use. Or maybe be configurable.
    return $this->statusScore($c);
    // return $this->statusIdleAgeScore($c);
  }

  /**
   * General priority:
   *   - Remove crashed processes
   *   - Then remove idle/exhausted processes
   *   - Then remove idle/non-exhausted processes
   *
   * @param \Civi\Coworker\PipeConnection $c
   * @return int
   */
  protected function statusScore(PipeConnection $c): int {
    $running = $c->isRunning();
    $idle = $c->isIdle();
    $exhausted = $c->isExhausted($this->config);

    if (!$running) {
      return 20;
    }
    if ($running && $idle && $exhausted) {
      return 10;
    }
    if ($running && $idle && !$exhausted) {
      return 5;
    }
    if ($running && !$idle) {
      return -10;
    }
    throw new \RuntimeException("Failed to score worker");
  }

  /**
   * General priority:
   *   - Remove crashed processes
   *   - Then remove idle/exhausted processes
   *   - Then remove idle/non-exhausted processes
   * Within that framework, we may break ties using the idleness or age.
   *
   * @param \Civi\Coworker\PipeConnection $c
   * @return int
   */
  protected function statusIdleAgeScore(PipeConnection $c): int {
    $running = $c->isRunning();
    $idle = $c->isIdle();

    // Positive scores are allowed to be removed (highest first). Negatives must be kept.
    if (!$running) {
      return 100;
    }
    if ($running && $idle) {
      $exhausted = $c->isExhausted($this->config);
      return 10 + ($exhausted ? 40 : 0) + $c->getIdleRank($this->config, 16) + $c->getAgeRank($this->config, 8);
    }
    if ($running && !$idle) {
      return -100;
    }
    throw new \RuntimeException("Failed to score worker");
  }

}
