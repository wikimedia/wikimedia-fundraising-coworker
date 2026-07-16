<?php

namespace Civi\Coworker\Client;

use Monolog\Logger;
use React\Promise\PromiseInterface;

/**
 * Client for CiviCRM based on a pool of many PipeConnections. It encodes and decodes Civi-specific
 * requests. Built primarily on CiviPipeClientTrait.
 *
 * CAUTION: This inherits session methods (`login(...)` and `options(...)`). However, I don't believe
 * it currently propagates the session options in a sensible fashion. It's OK right now because
 * we don't really use CiviPoolClient->login() or CiviPoolClient->options(). But these methods
 * should be overridden/revised.
 */
class CiviPoolClient implements CiviClientInterface {

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
