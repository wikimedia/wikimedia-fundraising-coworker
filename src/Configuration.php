<?php

namespace Civi\Coworker;

class Configuration {

  /**
   * Only if the local CiviCRM deployment meets this minimum requirement.
   * @var string
   *   Ex: '5.47.alpha1' for current Pipe/JSON-RPC format
   *   Ex: '5.51.alpha1' for current Queue API
   */
  public $minimumCivicrmVersion = '5.51.alpha1';

  /**
   * Maximum number of workers that may be running at the same time.
   *
   * @var int
   */
  public $maxConcurrentWorkers = 2;

  /**
   * Maximum amount of time (seconds) for which the overall system should run (inclusive of any/all workers).
   *
   * After reaching this limit, no more workers will be started, and no more tasks
   * will be executed.
   *
   * @var int|null
   */
  public $maxTotalDuration = NULL;

  /**
   * Maximum number of tasks to assign a single worker.
   *
   * After reaching this limit, no more tasks will be given to the worker.
   *
   * @var int
   */
  public $maxWorkerRequests = 10;

  /**
   * Maximum amount of time (seconds) for which a single worker should execute.
   *
   * After reaching this limit, no more tasks will be given to the worker.
   *
   * @var int
   */
  public $maxWorkerDuration = 10 * 60;

  /**
   * If the worker is idle for $X seconds, then shut it down.
   *
   * @var int
   */
  public $maxWorkerIdle = 60;

  /**
   * Whenever we hit the maximum, we have to remove some old workers.
   * How many should we try to remove?
   *
   * @var int
   */
  public $gcWorkers = 1;

  /**
   * External command used to start the pipe.
   *
   * @var string
   *   Ex: 'cv ev "Civi::pipe();"'
   */
  public $pipeCommand;

  /**
   * @var string
   */
  public $logFile;

  /**
   * Level of information to write to log file.
   *
   * One of: debug|info|notice|warning|error|critical|alert|emergency
   *
   * @var string
   */
  public $logLevel;

  /**
   * Should we enable polling-related debug info?
   *
   * The polling process sends a very large number of requests to the control-channel,
   * and most of these don't result in anything interesting. By default, we exclude
   * details about this from the log. However, you may re-enable it if you are
   * specifically debugging issues with the polling mechanism.
   *
   * @var bool
   */
  public $logPolling = FALSE;

  /**
   * Should we enable logging for the internal-queue mechanism?
   *
   * After claiming a task, it is momentarily placed on an internal-queue while
   * we find/setup resources for executing the task.
   *
   * @var bool
   */
  public $logInternalQueue = FALSE;

  /**
   * One of: text|json
   *
   * @var string
   */
  public $logFormat;

  public function __construct(array $values = []) {
    foreach ($values as $field => $value) {
      $this->{$field} = $value;
    }
  }

  public function __set($name, $value) {
    throw new \RuntimeException(sprintf('Unrecognized property: %s::$%s', __CLASS__, $name));
  }

}
