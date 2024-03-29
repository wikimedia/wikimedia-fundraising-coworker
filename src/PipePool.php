<?php

namespace Civi\Coworker;

use Civi\Coworker\Util\FunctionUtil;
use Civi\Coworker\Util\IdUtil;
use Civi\Coworker\Util\PromiseUtil;
use Monolog\Logger;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;
use function React\Promise\all;
use function React\Promise\resolve;

class PipePool {

  const QUEUE_INTERVAL = 0.1;

  /**
   * @var int
   * @readonly
   */
  public $id;

  /**
   * Keyed by ID
   * @var \Civi\Coworker\PipeConnection[]
   */
  private $connections = [];

  /**
   * Queue of pending requests that have not been submitted yet.
   *
   * @var Todo[]
   */
  private $todos = [];

  /**
   * @var \Civi\Coworker\Configuration
   */
  private $configuration;

  /**
   * @var \React\EventLoop\TimerInterface|null
   */
  private $timer;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  private $log;

  /**
   * The "connector" is responsible for applying initialization to any
   * new connections.
   *
   * @var callable
   *   Function(PipeConnection $connection, string $context): Promise
   */
  private $connector;

  public function __construct(Configuration $configuration, ?Logger $log = NULL, ?callable $connector = NULL) {
    $this->id = IdUtil::next(__CLASS__);
    $this->configuration = $configuration;
    $this->log = $log ?: new Logger('PipePool_' . $this->id);
    $this->connector = $connector;
  }

  /**
   * @return \React\Promise\PromiseInterface
   *   A promise which returns the online pool.
   */
  public function start(): PromiseInterface {
    $this->log->info("Start");
    $this->timer = Loop::addPeriodicTimer(static::QUEUE_INTERVAL, FunctionUtil::singular([$this, 'checkQueue']));
    return resolve($this);
  }

  /**
   * @param float $timeout
   *   If a process doesn't stop within $timeout, escalate to more aggressive forms of stopping.
   * @return \React\Promise\PromiseInterface
   *   A promise which returns when all subprocesses have stopped.
   */
  public function stop(float $timeout = 1.5): PromiseInterface {
    $this->log->info("Stopping");
    Loop::cancelTimer($this->timer);
    $this->timer = NULL;
    $all = [];
    foreach ($this->connections as $connection) {
      if (!$connection->isMoribund()) {
        $all[] = $connection->stop($timeout);
      }
      // TODO: Can we wait on the moribund ones that are already stopping?
    }
    return all($all);
  }

  /**
   * Dispatch a single request.
   *
   * @param string $context
   * @param string $requestLine
   * @return \React\Promise\PromiseInterface
   *   A promise for the response data.
   */
  public function dispatch(string $context, string $requestLine): \React\Promise\PromiseInterface {
    $this->log->debug("IntQueue: Send to \"{context}\": {requestLine}", ['context' => $context, 'requestLine' => $requestLine, 'isIntQueue' => TRUE]);
    $todo = new Todo($context, $requestLine);
    $this->todos[] = $todo;
    return $todo->deferred->promise();
  }

  /**
   * Periodic polling function. Check for new TODOs. Start/stop connections, as needed.
   *
   * @throws \Exception
   */
  public function checkQueue(): void {
    while (TRUE) {
      if (empty($this->todos)) {
        $this->cleanupIdleConnections();

        // Nothing to do... try again later...
        return;
      }

      /** @var \Civi\Coworker\Todo $todo */
      $todo = $this->todos[0];
      $startTodo = function() use ($todo) {
        if ($todo !== $this->todos[0]) {
          throw new \RuntimeException('Failed to dequeue expected task.');
        }
        array_shift($this->todos);
        $this->log->debug('IntQueue: Relaying', ['requestLine' => $todo->request, 'isIntQueue' => TRUE]);
      };

      // Re-use existing/idle connection?
      if ($connection = $this->findIdleConnection($todo->context)) {
        $startTodo();
        PromiseUtil::chain($connection->run($todo->request), $todo->deferred);
        continue;
      }

      // We want to make a new connection... Is there room?
      if (count($this->connections) >= $this->configuration->workerCount) {
        $this->cleanupConnections($this->configuration->workerCleanupCount);
      }

      if (count($this->connections) >= $this->configuration->workerCount) {
        // Not ready yet. Keep $todo in the queue and wait for later.
        return;
      }
      else {
        // OK, we can use this $todo... on a new connection.
        $startTodo();
        $this->addConnection($todo->context)->then(function ($connection) use ($todo) {
          PromiseUtil::chain($connection->run($todo->request), $todo->deferred);
        });
      }
    }
  }

  /**
   * Cleanup unnecessary references. These may include dead/disappeared workers, idle workers,
   * or workers that have been running for a long time.
   *
   * @param int $goalCount
   *   The number of workers we would like to remove.
   * @return int
   *   The number actually removed.
   */
  public function cleanupConnections(int $goalCount): int {
    // Score all workers - and decide which ones we can remove.
    $scorer = new PipePoolScorer($this->configuration);
    $sorted = new \SplPriorityQueue();
    foreach ($this->connections as $connection) {
      /** @var \Civi\Coworker\PipeConnection $connection */
      $score = $scorer->score($connection);
      $this->log->debug('cleanupConnections: scored {conn} as {score}', ['conn' => $connection->toString(), 'score' => $score]);
      if ($score > 0) {
        $sorted->insert($connection, $score);
      }
    }

    $removedCount = 0;
    foreach ($sorted as $connection) {
      /** @var \Civi\Coworker\PipeConnection $connection */
      if ($removedCount >= $goalCount) {
        break;
      }
      $this->log->debug('cleanupConnections: remove {conn}', ['conn' => $connection->toString()]);
      $this->removeConnection($connection->id);
      $removedCount++;
    }
    $this->log->debug('cleanupConnections: removed {count} connection(s)', ['count' => $removedCount]);
    return $removedCount;
  }

  /**
   * Find any connections which have been active for more than ($workerTimeout) seconds.
   * Stop them.
   *
   * @return \React\Promise\PromiseInterface
   */
  private function cleanupIdleConnections(): PromiseInterface {
    $promises = [];
    foreach ($this->connections as $connection) {
      if ($connection->isIdle() && $connection->getIdleDuration() > $this->configuration->workerTimeout) {
        $promises[] = $this->removeConnection($connection->id);
      }
    }
    return all($promises);
  }

  private function findIdleConnection(string $context): ?PipeConnection {
    foreach ($this->connections as $connection) {
      if ($connection->context === $context && $connection->isIdle() && !$connection->isExhausted($this->configuration)) {
        return $connection;
      }
    }
    return NULL;
  }

  /**
   * Determine if the pool has any "open" slots. A slot is considered open if
   * either (a) there is no worker in the slot or (b) there is an idle worker.
   *
   * @return bool
   */
  public function hasOpenSlot(): bool {
    if (count($this->connections) < $this->configuration->workerCount) {
      return TRUE;
    }
    foreach ($this->connections as $connection) {
      if ($connection->isIdle()) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * @param string $context
   * @return \React\Promise\PromiseInterface
   *   A promise for the new/started instance of PipeConnection.
   */
  private function addConnection(string $context): PromiseInterface {
    $connection = new PipeConnection($this->configuration, $context, $this->log);
    $this->connections[$connection->id] = $connection;
    $this->log->debug('Starting connection #{id}', ['id' => $connection->id]);
    return $connection->start()->then(function($welcome) use ($connection, $context) {
      if (!$this->connector) {
        $this->log->debug('Started connection #{id} for #{context}', ['id' => $connection->id, 'welcome' => $welcome, 'context' => $context]);
        return $connection;
      }
      $this->log->debug('Initializing connection #{id} for #{context}', ['id' => $connection->id, 'welcome' => $welcome, 'context' => $context]);
      return call_user_func($this->connector, $connection, $context)
        ->then(function() use ($connection, $welcome, $context) {
          $this->log->debug('Started connection #{id} for #{context}', ['id' => $connection->id, 'welcome' => $welcome, 'context' => $context]);
          return $connection;
        });
    });
  }

  /**
   * @param int|string $connectionId
   * @return \React\Promise\PromiseInterface
   *   A promise for the stopped instance of PipeConnection.
   */
  private function removeConnection($connectionId): PromiseInterface {
    $connection = $this->connections[$connectionId];
    unset($this->connections[$connectionId]);
    return $connection->stop()->then(function() use ($connection) {
      $this->log->debug('Stopped connection');
      return $connection;
    });
  }

}
