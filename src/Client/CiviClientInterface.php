<?php

namespace Civi\Coworker\Client;

use React\Promise\PromiseInterface;

interface CiviClientInterface {

  /**
   * Send an APIv3 request.
   *
   * @param string $entity
   * @param string $action
   * @param array $params
   * @return \React\Promise\PromiseInterface
   * @see \civicrm_api3()
   */
  public function api3(string $entity, string $action, array $params = []): PromiseInterface;

  /**
   * Send an APIv4 request.
   *
   * @param string $entity
   * @param string $action
   * @param array $params
   * @return \React\Promise\PromiseInterface
   * @see \civicrm_api4()
   */
  public function api4(string $entity, string $action, array $params = []): PromiseInterface;

}
