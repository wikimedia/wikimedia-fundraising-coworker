<?php

namespace Civi\Coworker;

use Civi\Coworker\Client\CiviClientInterface;
use Civi\Coworker\Client\CiviJsonRpcClientTrait;
use Monolog\Logger;
use React\Promise\PromiseInterface;
use function React\Promise\reject;

/**
 * Wrapper for PipeConnection which encodes and decodes Civi-specific
 * requests.
 */
class CiviPipeConnection implements CiviClientInterface {

  const MINIMUM_VERSION = '5.47.alpha1';
  //  const MINIMUM_VERSION = '5.49.alpha1';

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
   * @param \Civi\Coworker\PipeConnection $pipeConnection
   * @param \Monolog\Logger|NULL $logger
   */
  public function __construct($pipeConnection, ?\Monolog\Logger $logger) {
    $this->pipeConnection = $pipeConnection;
    $this->logger = $logger ?: new Logger(static::CLASS);
  }

  use CiviJsonRpcClientTrait;

  /**
   * @return \React\Promise\PromiseInterface
   *   Promise<array> - List of header flags
   * @see \Civi\Coworker\PipeConnection::start()
   */
  public function start(): PromiseInterface {
    return $this->pipeConnection->start()
      ->then(function (string $welcomeLine) {
        $welcome = json_decode($welcomeLine, 1);
        if (!isset($welcome['Civi::pipe'])) {
          return reject(new \Exception('Malformed header: ' . $welcomeLine));
        }
        if (empty($welcome['Civi::pipe']['v']) || version_compare($welcome['Civi::pipe']['v'], self::MINIMUM_VERSION, '<')) {
          return reject(new \Exception(sprintf("Expected minimum CiviCRM version %s. Received welcome: %s\n", self::MINIMUM_VERSION, $welcomeLine)));
        }
        $this->welcome = $welcome;
        $this->logger->notice('Connected', ['Civi::pipe' => $welcome['Civi::pipe']]);
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
