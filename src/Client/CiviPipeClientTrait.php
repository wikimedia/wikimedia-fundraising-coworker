<?php

namespace Civi\Coworker\Client;

use Civi\Coworker\Util\IdUtil;
use Civi\Coworker\Util\JsonRpc;
use React\Promise\PromiseInterface;

/**
 * Helper class for implementing a CiviCRM API client.
 *
 * This implements most of the methods for `CiviClientInterface` and `CiviSessionInterface`, but
 * it requires you to provide an implementation of `function sendJsonRpc(string $requestLine): Promise<string>`.
 *
 * @see \Civi\Coworker\Client\CiviClientInterface
 * @see \Civi\Coworker\Client\CiviSessionInterface
 */
trait CiviPipeClientTrait {

  /**
   * Maximum request ID.
   *
   * JSON-RPC requires attaching an ID to every request. We don't really use this ID, but we will
   * send a lot of requests over time, and eventually we need to loopback. TBH, you could probably
   * get away with setting this to 1, but maybe it's handy for debugging to have IDs more spread out.
   *
   * @var int
   */
  private static $maxRequestId = 1000 * 1000;

  /**
   * Send a line of JSON. Receive a line of JSON.
   *
   * @param string $requestLine
   * @return \React\Promise\PromiseInterface
   *   Promise yielding JSON string (response line).
   */
  abstract protected function sendJsonRpc(string $requestLine): PromiseInterface;

  public function request(string $method, array $params = [], ?string $caller = NULL): PromiseInterface {
    $id = IdUtil::next(__CLASS__ . '::request', static::$maxRequestId);

    $requestLine = JsonRpc::createRequest($method, $params, $id);
    $request = ['method' => $method, 'params' => $params, 'caller' => $caller];
    $this->logger->debug(sprintf('Send request #%s: %s', $id, $requestLine));
    return $this->sendJsonRpc($requestLine)
      ->then(function(string $responseLine) use ($request, $id) {
        $this->logger->debug(sprintf('Receive response #%s: %s', $id, $responseLine));
        return JsonRpc::parseResponse($responseLine, $id, $request);
      });
  }

  /**
   * @param string $entity
   * @param string $action
   * @param array $params
   * @return \React\Promise\PromiseInterface
   * @see \Civi\Pipe\PublicMethods::api3()
   */
  public function api3(string $entity, string $action, array $params = []): PromiseInterface {
    return $this->request('api3', [$entity, $action, $params], $this->findCaller(2));
  }

  /**
   * @param string $entity
   * @param string $action
   * @param array $params
   * @return \React\Promise\PromiseInterface
   * @see \Civi\Pipe\PublicMethods::api4()
   */
  public function api4(string $entity, string $action, array $params = []): PromiseInterface {
    return $this->request('api4', [$entity, $action, $params], $this->findCaller(2));
  }

  /**
   * Login to the CiviCRM instance.
   *
   * FIXME: This implementation is suitable for CiviPipeClient, but it will be awkward with CiviPoolClient.
   * For CiviPoolClient, we need a way to remember these options and re-apply them for new sub-processes.
   *
   * @param array $params
   * @return \React\Promise\PromiseInterface
   * @see \Civi\Pipe\PublicMethods::login()
   */
  public function login(array $params): PromiseInterface {
    return $this->request('login', $params, $this->findCaller(2));
  }

  /**
   * Configure any session-options.
   *
   * FIXME: This implementation is suitable for CiviPipeClient, but it will be awkward with CiviPoolClient.
   * For CiviPoolClient, we need a way to remember these options and re-apply them for new sub-processes.
   *
   * @param array $params
   * @return \React\Promise\PromiseInterface
   * @see \Civi\Pipe\PublicMethods::options()
   */
  public function options(array $params): PromiseInterface {
    return $this->request('options', $params, $this->findCaller(2));
  }

  private function findCaller(int $steps): string {
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $steps);
    $caller = array_pop($trace);
    $result = sprintf('%s @ %s', $caller['file'], $caller['line']);
    return $result;
  }

}
