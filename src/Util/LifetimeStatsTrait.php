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

  /**
   * Quantify the relative idleness of this object.
   *
   * @param \Civi\Coworker\Configuration $configuration
   * @param int $scale
   *   Ex: 10
   * @return int
   *   Ex: A value between 0 and 10.
   *   Ex: A value 2/10 indicates the object has gone ~20% of the way towards idle timeout.
   *   Higher values indicate longer periods of idleness.
   */
  public function getIdleRank(Configuration $configuration, int $scale): int {
    return $this->idleSince ? floor($scale * $this->getIdleDuration() / $configuration->maxWorkerIdle) : 0;
  }

  /**
   * Quantify the relative age of this object.
   *
   * @param \Civi\Coworker\Configuration $configuration
   * @param int $scale
   *   Ex: 10
   * @return int
   *   Ex: A value between 0 and 10.
   *   Ex: A value 2/10 indicates the object has gone ~20% of the way towards maximum duration.
   *   Higher values indicate older objects.
   */
  public function getAgeRank(Configuration $configuration, int $scale): int {
    return $this->startTime ? floor($scale * (microtime(1) - $this->startTime) / $configuration->maxWorkerDuration) : 0;
  }

}
