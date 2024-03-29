<?php

namespace Civi\Coworker;

use Civi\Coworker\Client\CiviClientInterface;
use Civi\Coworker\Util\TaskSplitter;
use Evenement\EventEmitterTrait;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Promise\all;
use function React\Promise\resolve;

/**
 * Monitor CiviCRM queues for new tasks - and execute them.
 *
 * The basic process is a round-robin scan (visiting each queue, 1-by-1);
 * it runs in a loop with these steps:
 *
 * 1. fillSteps(): Get a list of live queues. Plan a step for visiting each.
 * 2. runQueueItem($queueName): Check each queue one-by-one. Claim their front-most task (if any)
 *    and then execute it (via PipePool).
 * 3. finishInterval(): Sleep for a moment in between scans. Ensure that we don't run more than 1
 *    scan per {$pollInterval}.
 * 4. Go back to step #1.
 *
 * This loop begins with a call to `start()` and terminates with a call to `stop()`.
 * When you call `stop()`, the current loop will wrap-up before finishing.
 */
class CiviQueueWatcher {

  use EventEmitterTrait;

  /**
   * @var \Civi\Coworker\Configuration
   */
  protected $config;

  /**
   * FIXME: Shouldn't we be restrting the ctl connection periodically?
   * Maybe it should build on a PipePool of workerCount=1?
   *
   * @var \Civi\Coworker\Client\CiviClientInterface
   */
  protected $ctl;

  /**
   * @var \Civi\Coworker\PipePool
   */
  protected $pipePool;

  /**
   * @var \Monolog\Logger
   */
  protected $logger;

  /**
   * @var \React\EventLoop\TimerInterface
   */
  protected $timer;

  /**
   * @var \Clue\React\Mq\Queue
   */
  protected $q;

  /**
   * @var \React\Promise\Deferred
   */
  protected $moribundDeferred;

  /**
   * @param \Civi\Coworker\Configuration $config
   * @param \Civi\Coworker\Client\CiviClientInterface $ctl
   * @param \Civi\Coworker\PipePool $pipePool
   * @param \Monolog\Logger $logger
   */
  public function __construct(Configuration $config, CiviClientInterface $ctl, PipePool $pipePool, \Monolog\Logger $logger) {
    $this->config = $config;
    $this->ctl = $ctl;
    $this->pipePool = $pipePool;
    $this->logger = $logger;
  }

  /**
   * Start watching the Civi queues and executing their tasks.
   *
   * @return \React\Promise\PromiseInterface
   *   Notifies when the queue-watcher has started
   *   its round-robin polling.
   *
   *   Tip: If you instead want a notification when the queue-watcher
   *   has wrapped up all operations, use `$queueWatcher->on('stop', ...)`.
   */
  public function start(): PromiseInterface {
    $this->logger->info('Starting...');
    $this->lastFillTime = NULL;
    // The round-robin scan has a variable number of steps.
    // We store these steps in a local, sequential (concurrency=1) data-store.
    $this->addStep = new \Clue\React\Mq\Queue(1, NULL, function ($args) {
      return $this->onNextStep($args);
    });
    Loop::addTimer($this->config->pollInterval, function() {
      $this->addStep(['fillSteps']);
    });
    return resolve();
  }

  public function stop(): PromiseInterface {
    $this->logger->info('Stopping...');
    $this->moribundDeferred = new Deferred();
    return $this->moribundDeferred->promise();
  }

  protected function addStep($step): PromiseInterface {
    return call_user_func($this->addStep, $step);
  }

  protected function fillSteps(): PromiseInterface {
    if ($this->moribundDeferred) {
      $this->logger->debug('Finished pending queue tasks');
      $this->addStep = NULL;
      $this->lastFillTime = NULL;
      $this->moribundDeferred->resolve();
      $this->emit('stop');
      return resolve();
    }
    $this->logger->debug('Poll queues', ['isPolling' => TRUE]);
    $this->lastFillTime = microtime(1);
    return $this->ctl->api4('Queue', 'get', $this->config->pollQuery)
      ->then(function ($queues) {
        foreach ($queues as $queue) {
          $this->addStep(['runQueueItem', $queue['name']]);
        }
        $this->addStep(['finishInterval']);
        $this->addStep(['fillSteps']);
        // $this->logger->debug('Polling: Check {count} queues', ['count' => count($queues)]);
      }, function($error) {
        // The latest request failed... but maybe we can try again...
        // Note: If we don't try again, then `fillSteps()` will never detect shutdown.
        $this->logger->error('Failed to read queues!', ['exception' => $error]);
        $this->addStep(['finishInterval']);
        $this->addStep(['fillSteps']);
      });
  }

  protected function runQueueItem(string $queueName): PromiseInterface {
    $this->logger->debug('Poll queue ({name})', ['name' => $queueName, 'isPolling' => TRUE]);
    $item = NULL;
    return $this->ctl
      ->api4('Queue', 'claimItems', ['queue' => $queueName, 'select' => ['id', 'queue', 'run_as']])
      ->then(function ($allItems) use ($queueName, &$item) {
        // claimItem is specified to return 0 or 1 items.
        if (empty($allItems)) {
          $this->logger->debug('Nothing in queue {name}', ['name' => $queueName, 'isPolling' => TRUE]);
          return resolve();
        }

        $subgroups = TaskSplitter::split($allItems);
        $promises = [];
        foreach ($subgroups as $subgroup) {
          $context = $subgroup['context'];
          $this->logger->notice('Claimed queue item(s) for {context}: {items}', $subgroup);
          $client = new Client\CiviPoolClient($this->pipePool, $context, $this->logger->withName("CiviPool[$context]"));
          $promises[$context] = $client->api4('Queue', 'runItems', [
            'items' => $subgroup['items'],
            'checkPermissions' => FALSE,
          ])->then([$this, 'onRunItemResults']);
        }
        return all($promises);
      });
  }

  /**
   * The `Queue.runItems` returns a list of outcomes. Look at it and log interesting outcomes.
   *
   * @param array $results
   */
  protected function onRunItemResults(array $results): void {
    // TODO: Is this really getting called?
    foreach ($results as $result) {
      switch ($result['outcome']) {
        case 'fail':
        case 'retry':
          $this->logger->warning('({queue}#{id}) returned "{outcome}"', $result['item'] + ['outcome' => $result['outcome']]);
          break;
      }
    }
  }

  /**
   * After polling the Civi queue(s), we may need to sleep a moment
   * before polling again.
   *
   * @return \React\Promise\PromiseInterface
   */
  protected function finishInterval(): PromiseInterface {
    $now = microtime(1);
    $nextFillTime = $this->lastFillTime + $this->config->pollInterval;
    $waitTime = $nextFillTime - $now;
    return $waitTime > 0 ? \React\Promise\Timer\resolve($waitTime) : resolve();
  }

  protected function onNextStep(array $args): PromiseInterface {
    $verb = array_shift($args);
    switch ($verb) {
      case 'fillSteps':
      case 'runQueueItem':
      case 'finishInterval':
        return $this->{$verb}(...$args);

      break;

      default:
        return reject(new \Exception('Invalid item in task loop: ' . $verb));
    }
  }

}
