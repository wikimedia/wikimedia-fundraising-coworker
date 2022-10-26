<?php

namespace Civi\Coworker\Client;

use Civi\Coworker\Configuration;
use Civi\Coworker\Util\JsonRpc;
use Monolog\Logger;
use React\Promise\PromiseInterface;
use function React\Promise\reject;

/**
 * Wrapper for PipeConnection which encodes and decodes Civi-specific
 * requests.
 */
class CiviPipeClient implements CiviClientInterface {

  /**
   * @var \Civi\Coworker\Configuration
   */
  protected $config;

  /**
   * @var \Civi\Coworker\PipeConnection
   */
  protected $pipeConnection;

  /**
   * @var \Monolog\Logger
   */
  protected $logger;

  /**
   * @var array
   */
  protected $welcome;

  /**
   * @param \Civi\Coworker\Configuration $config
   * @param \Civi\Coworker\PipeConnection $pipeConnection
   * @param \Monolog\Logger|NULL $logger
   */
  public function __construct(Configuration $config, $pipeConnection, ?\Monolog\Logger $logger = NULL) {
    $this->config = $config;
    $this->pipeConnection = $pipeConnection;
    $this->logger = $logger ?: new Logger(static::CLASS);
  }

  use CiviPipeClientTrait;

  /**
   * @return \React\Promise\PromiseInterface
   *   Promise<array> - List of header flags
   * @see \Civi\Coworker\PipeConnection::start()
   */
  public function start(): PromiseInterface {
    return $this->pipeConnection->start()
      ->then(function (string $welcomeLine) {
        try {
          $welcome = JsonRpc::parseWelcome($welcomeLine, $this->config->minimumCivicrmVersion);
        }
        catch (\Exception $e) {
          return reject($e);
        }

        $this->welcome = $welcome['Civi::pipe'];
        $this->logger->info('Connected ({welcome})', ['welcome' => $this->welcome]);
        return $welcome['Civi::pipe'];
      });
  }

  /**
   * @param float $timeout
   * @return \React\Promise\PromiseInterface
   * @see \Civi\Coworker\PipeConnection::stop
   */
  public function stop(float $timeout = 1.5): PromiseInterface {
    return $this->pipeConnection->stop($timeout);
  }

  protected function sendJsonRpc(string $requestLine): PromiseInterface {
    return $this->pipeConnection->run($requestLine);
  }

}
