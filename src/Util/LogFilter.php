<?php

namespace Civi\Coworker\Util;

use Civi\Coworker\Configuration;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\HandlerWrapper;
use Monolog\Logger;

class LogFilter extends HandlerWrapper {

  /**
   * @var \Civi\Coworker\Configuration
   */
  protected $config;

  protected $minLevel;

  public function __construct(Configuration $config, HandlerInterface $handler) {
    parent::__construct($handler);
    $this->config = $config;
    $this->minLevel = Logger::toMonologLevel($config->logLevel);
  }

  public function isHandling(array $record): bool {
    if ($record['level'] < $this->minLevel) {
      return FALSE;
    }

    if (!parent::isHandling($record)) {
      return FALSE;
    }

    $channel = $record['channel'] ?? '';

    if (!$this->config->logPolling && $record['level'] <= Logger::DEBUG) {
      if (preg_match('/^Pipe\[ctl#/', $channel)) {
        return FALSE;
      }
      if ($channel === 'CtlClient') {
        return FALSE;
      }
      if (!empty($record['context']['isPolling'])) {
        return FALSE;
      }
    }

    if (!$this->config->logInternalQueue && $record['level'] <= Logger::DEBUG) {
      if (preg_match('/^CiviPool\[/', $channel)) {
        return FALSE;
      }
      if (!empty($record['context']['isIntQueue'])) {
        return FALSE;
      }
    }

    return TRUE;
  }

  public function handle(array $record): bool {
    if (!$this->isHandling($record)) {
      return FALSE;
    }

    return parent::handle($record);
  }

}
