<?php

namespace Civi\Coworker;

use Civi\Coworker\Client\CiviClientInterface;
use function Clue\React\Block\await;

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
  public $workerCount = 2;

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
  public $workerRequests = 10;

  /**
   * Maximum amount of time (seconds) for which a single worker should execute.
   *
   * After reaching this limit, no more tasks will be given to the worker.
   *
   * @var int
   */
  public $workerDuration = 10 * 60;

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

  /**
   * How often are we allowed to poll the queues for new items? (#seconds)
   *
   * Lower values will improve responsiveness - and increase the number of queries.
   *
   * Note that there may be multiple queues to poll, and each poll operation may take
   * some #milliseconds. This number is not a simple `sleep()`; rather, it is a target.
   * After doing a round of polling, we will sleep as long as necessary in
   * order to meet the $pollInterval.
   *
   * @var float
   */
  public $pollInterval = 0.66;

  /**
   * Query to use for selecting the list of target queues.
   *
   * If omitted, a query will be chosen after inspecting the CiviCRM runtime.
   *
   * Ex: ['where' => [['runner', 'IS NOT EMPTY'], ['status', '=', 'active']]]
   *
   * @var array
   */
  public $queueFilter;

  public function __construct(array $values = []) {
    foreach ($values as $field => $value) {
      $this->{$field} = $value;
    }
  }

  public function __set($name, $value) {
    throw new \RuntimeException(sprintf('Unrecognized property: %s::$%s', __CLASS__, $name));
  }

  public function loadOptions(array $options): void {
    $aliases = [
      // Old name => New name
      'maxConcurrentWorkers' => 'workerCount',
      'maxWorkerDuration' => 'workerDuration',
      'maxWorkerRequests' => 'workerRequests',
    ];

    foreach ($options as $cfgOption => $inputValue) {
      if (isset($aliases[$cfgOption])) {
        // trigger_error(sprintf('DEPRECATED: Configuration option "%s" renamed to "%s"', $cfgOption, $aliases[$cfgOption]), E_USER_DEPRECATED);
        error_log(sprintf('DEPRECATED: Configuration option "%s" renamed to "%s"', $cfgOption, $aliases[$cfgOption]));
        $cfgOption = $aliases[$cfgOption];
      }
      $this->{$cfgOption} = $inputValue;
    }
  }

  /**
   * After getting a control-channel, inspect the system and fine-tune the configuration.
   *
   * @param array $welcome
   * @param \Civi\Coworker\Client\CiviClientInterface $ctlChannel
   *   Reference to the control-channel.
   * @throws \Exception
   */
  public function onConnect(array $welcome, CiviClientInterface $ctlChannel) {
    if ($this->queueFilter === NULL) {
      $this->queueFilter = $this->pickQueueFilter($ctlChannel);
    }
  }

  private function pickQueueFilter(CiviClientInterface $ctl): array {
    $queueFields = await($ctl->api4('Queue', 'getFields', ['select' => ['name']]));
    $queueFieldNames = array_column($queueFields, 'name');
    if (in_array('agent', $queueFieldNames)) {
      // 5.47 - 5.67
      return ['where' => [['agent', 'CONTAINS', 'server'], ['status', '=', 'active']]];
    }
    else {
      // 5.68+
      return ['where' => [['runner', 'IS NOT EMPTY'], ['status', '=', 'active']]];
    }
  }

}
