<?php

namespace Civi\Coworker\Client;

use Monolog\Logger;
use React\Promise\PromiseInterface;

class CiviPoolClient implements CiviClientInterface, CiviSessionInterface {

  use CiviPipeClientTrait;

  /**
   * @var \Civi\Coworker\PipePool
   */
  protected $pipePool;

  /**
   * @var string|null
   */
  protected $context;

  /**
   * @var \Monolog\Logger
   */
  protected $logger;

  /**
   * @param \Civi\Coworker\PipePool $pipePool
   * @param string|null $context
   * @param \Monolog\Logger|NULL $logger
   */
  public function __construct(\Civi\Coworker\PipePool $pipePool, ?string $context, ?\Monolog\Logger $logger = NULL) {
    $this->pipePool = $pipePool;
    $this->context = $context;
    $this->logger = $logger ?: new Logger(static::CLASS);
  }

  protected function sendJsonRpc(string $requestLine): PromiseInterface {
    return $this->pipePool->dispatch($this->context, $requestLine);
  }

}
