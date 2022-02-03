<?php

namespace Civi\Coworker\Client;

use React\Promise\PromiseInterface;

interface CiviClientInterface {

  public function api3(string $entity, string $action, array $params = []): PromiseInterface;

  public function api4(string $entity, string $action, array $params = []): PromiseInterface;

}
