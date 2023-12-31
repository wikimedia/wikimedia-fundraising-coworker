<?php

namespace Civi\Coworker\Util;

use Civi\Coworker\Configuration;

trait LifetimeStatsTrait {

  /**
   * @var double|null
   */
  protected $startTime = NULL;

  /**
   * @var double|null
   */
  protected $endTime = NULL;

  /**
   * The time at which this unit became idle.
   *
   * @var double|null
   */
  protected $idleSince = NULL;

  /**
   * @var int
   */
  protected $requestCount = 0;

  protected $moribund = FALSE;

  /**
   * @return float|null
   */
  public function getStartTime(): ?float {
    return $this->startTime;
  }

  /**
   * @return float|null
   */
  public function getEndTime(): ?float {
    return $this->endTime;
  }

  /**
   * @return int
   */
  public function getRequestCount(): int {
    return $this->requestCount;
  }

  /**
   * @return bool
   */
  public function isMoribund(): bool {
    return $this->moribund;
  }

  /**
   * @param bool $moribund
   */
  public function setMoribund(bool $moribund): void {
    $this->moribund = $moribund;
  }

  public function isExhausted(Configuration $configuration): bool {
    if ($this->moribund) {
      return TRUE;
    }
    if ($this->requestCount >= $configuration->maxWorkerRequests) {
      return TRUE;
    }
    if ($this->startTime + $configuration->maxWorkerDuration < microtime(TRUE)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Mark this object as idle.
   */
  public function idling(): void {
    $this->idleSince = $this->idleSince ?: microtime(1);
  }

  /**
   * Mark this object as not idle.
   */
  public function notIdling(): void {
    $this->idleSince = NULL;
  }

  /**
   * Determine how long the object has been idle.
   *
   * @return float
   */
  public function getIdleDuration(): float {
    return $this->idleSince ? (microtime(1) - $this->idleSince) : 0;
  }

}
