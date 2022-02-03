<?php

namespace Civi\Coworker\Client;

use React\Promise\PromiseInterface;

interface CiviPipeClientInterface extends CiviClientInterface {

  public function login(array $params): PromiseInterface;

  public function options(array $params): PromiseInterface;

}
