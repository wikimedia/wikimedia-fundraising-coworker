<?php

namespace Civi\Coworker\Client;

use React\Promise\PromiseInterface;

interface CiviPipeClientInterface extends CiviClientInterface {

  /**
   * Set the current user.
   *
   * Requires `authx` on the remote system.
   *
   * @param array{contactId: int, userId: int, user: string, cred: string} $request
   *   Untrusted sessions may authenticate with a `cred`.
   *   Trusted sessions may simply set the active principal (contact or user).
   *
   * @return \React\Promise\PromiseInterface
   *   Description of the authenticated party (`contactId` and/or `userId`).
   * @see \Civi\Pipe\PublicMethods::login()
   */
  public function login(array $request): PromiseInterface;

  /**
   * Change session options.
   *
   * @param array{bufferSize: int, responsePrefix: int} $request
   * @return \React\Promise\PromiseInterface
   *   List of updated options.
   *   If the list of updates was empty, then return all options.
   * @see \Civi\Pipe\PublicMethods::options()
   */
  public function options(array $request): PromiseInterface;

}
