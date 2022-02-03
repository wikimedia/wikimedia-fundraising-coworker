<?php

namespace Civi\Coworker\Util;

use Civi\Coworker\Exception\JsonRpcMethodException;
use Civi\Coworker\Exception\JsonRpcProtocolException;
use React\Promise\PromiseInterface;
use function React\Promise\reject;
use function React\Promise\resolve;

class JsonRpc {

  const MINIMUM_VERSION = '5.47.alpha1';

  public static function parseWelcome(string $welcomeLine): array {
    $welcome = json_decode($welcomeLine, 1);
    if (!isset($welcome['Civi::pipe'])) {
      throw new \Exception('Malformed header: ' . $welcomeLine);
    }
    if (empty($welcome['Civi::pipe']['v']) || version_compare($welcome['Civi::pipe']['v'], self::MINIMUM_VERSION, '<')) {
      throw new \Exception(sprintf("Expected minimum CiviCRM version %s. Received welcome: %s\n", self::MINIMUM_VERSION, $welcomeLine));
    }
    return $welcome;
  }

  public static function createRequest(string $method, array $params = [], $id = NULL): string {
    return json_encode(['jsonrpc' => '2.0', 'method' => $method, 'params' => $params, 'id' => $id]);
  }

  public static function parseResponse(string $responseLine, $id = NULL, array $request = []): PromiseInterface {
    $decode = json_decode($responseLine, TRUE);
    if (!isset($decode['jsonrpc']) || $decode['jsonrpc'] !== '2.0') {
      return reject(new JsonRpcProtocolException("Protocol error: Response lacks JSON-RPC header."));
    }
    if (!array_key_exists('id', $decode) || $decode['id'] !== $id) {
      return reject(new JsonRpcProtocolException("Protocol error: Received response for wrong request."));
    }

    if (array_key_exists('error', $decode) && !array_key_exists('result', $decode)) {
      return reject(new JsonRpcMethodException($decode, $request));
    }
    if (array_key_exists('result', $decode) && !array_key_exists('error', $decode)) {
      return resolve($decode['result']);
    }
    return reject(new JsonRpcProtocolException("Protocol error: Response must include 'result' xor 'error'."));

  }

}
