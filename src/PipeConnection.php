<?php

namespace Civi\Coworker;

use Civi\Coworker\Util\IdUtil;
use Civi\Coworker\Util\LifetimeStatsTrait;
use Civi\Coworker\Util\LineReader;
use Civi\Coworker\Util\ProcessUtil;
use Monolog\Logger;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

/**
 * Setup a pipe-based connection. This starts the subprocess and provides a
 * method to send one-line requests and receive one-line responses.
 *
 * ```php
 * $p = new PipeConnection(new Configuration('cv ev "Civi::pipe();"');
 * $p->send('SOME COMMAND')->then(function($response){...});
 * ```
 */
class PipeConnection {

  use LifetimeStatsTrait;

  /**
   * @var int
   * @readonly
   */
  public $id;

  /**
   * @var string
   * @readonly
   */
  public $context;

  /**
   * @var Configuration
   */
  public $configuration;

  private $delimiter = "\n";

  /**
   * @var \React\ChildProcess\Process
   */
  protected $process;

  /**
   * @var \Civi\Coworker\Util\LineReader
   */
  protected $lineReader;

  /**
   * If there is a pending request, this is the deferred use to report on the request.
   * If there is no pending request, then null.
   *
   * @var \React\Promise\Deferred|null
   */
  protected $deferred;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $log;

  public function __construct(Configuration $configuration, string $context, ?Logger $logger = NULL) {
    $this->id = $context . '#' . IdUtil::next(__CLASS__ . ';' . $context);
    $this->context = $context;
    $this->configuration = $configuration;
    $this->deferred = NULL;
    $this->idling();

    $name = "Pipe[{$this->id}]";
    $this->log = $logger ? $logger->withName($name) : new Logger($name);
    $this->log->pushProcessor(function($rec) {
      $rec['childPid'] = $this->process ? $this->process->getPid() : '?';
      $rec['parentPid'] = posix_getpid();
      return $rec;
    });
  }

  /**
   * Launch the worker process.
   *
   * @return \React\Promise\PromiseInterface
   *   The promise returns when the pipe starts.
   *   It will report the welcome line.
   */
  public function start(): PromiseInterface {
    $this->log->info("Start: {cmd}", ['cmd' => $this->configuration->pipeCommand]);
    $this->startTime = microtime(TRUE);

    // We will receive a 1-line welcome which signals that startup has finished.
    $this->reserveDeferred();

    $this->process = new \React\ChildProcess\Process($this->configuration->pipeCommand, NULL, $this->buildEnv());
    $this->process->start();
    // $this->process->stdin->on('data', [$this, 'onReceive']);
    $this->lineReader = new LineReader($this->process->stdout, $this->delimiter);
    $this->lineReader->on('readline', [$this, 'onReadLine']);
    $this->process->stderr->on('data', [$this, 'onReceiveError']);
    $this->process->on('exit', function ($exitCode, $termSignal) {
      $this->endTime = microtime(TRUE);
      if ($this->deferred !== NULL) {
        $oldDeferred = $this->deferred;
        $this->deferred = NULL;
        $this->idling();
        $oldDeferred->reject('Process exited');
      }
    });

    $this->log->debug("Forked");
    return $this->deferred->promise();
  }

  /**
   * Send a request to the worker, and receive an async response.
   *
   * Worker protocol only allows one active request. If you send a second
   * request while the first remains pending, it will be rejected.
   *
   * @param string $requestLine
   *
   * @return \React\Promise\PromiseInterface
   *   Promise produces a `string` for the one-line response.
   * @throws \Exception
   */
  public function run($requestLine): \React\Promise\PromiseInterface {
    $this->requestCount++;
    $deferred = $this->reserveDeferred();

    if (!$this->process->isRunning()) {
      $this->releaseDeferred();
      $this->log->error("Worker disappeared. Cannot send request", ['requestLine' => $requestLine]);
      $deferred->reject("Worker disappeared. Cannot send: $requestLine");
      return $deferred->promise();
    }

    $this->log->debug("Send request: $requestLine");
    $this->process->stdin->write($requestLine . $this->delimiter);
    return $deferred->promise();
  }

  /**
   * Shutdown the worker.
   *
   * If there is a pending request, it may be aborted and may report failure.
   *
   * @param float $timeout
   *   stop() will initially try a gentle shutdown by closing STDIN.
   *   If it doesn't end within $timeout, it will escalate the means of stopping.
   * @return \React\Promise\PromiseInterface
   *   A promise that returns once shutdown is complete.
   * @throws RuntimeException
   *   If you attempt to stop multiple times, subsequent calls will throw an exception.
   */
  public function stop(float $timeout = 1.5): PromiseInterface {
    if ($this->moribund) {
      throw new \RuntimeException("Process is already stopping or stopped.");
    }
    $this->setMoribund(TRUE);
    $this->log->info('Stopping');
    return ProcessUtil::terminateWithEscalation($this->process, $timeout)
      ->then(function($data) {
        $this->log->info('Stopped');
        return $data;
      });
  }

  /**
   * Is the agent currently idle - or busy with a request?
   *
   * @return bool
   */
  public function isIdle(): bool {
    return $this->deferred === NULL;
  }

  /**
   * Is the agent currently online?
   *
   * @return bool
   */
  public function isRunning(): bool {
    return $this->process->isRunning();
  }

  /**
   * @param string $responseLine
   * @internal
   */
  public function onReadLine(string $responseLine): void {
    $this->log->debug("Receive response: $responseLine");
    if ($this->deferred) {
      $this->releaseDeferred()->resolve($responseLine);
    }
    elseif ($responseLine !== '') {
      $this->log->error('Received unexpected response line: "{responseLine}"', ['responseLine' => $responseLine]);
    }
  }

  /**
   * @param $responseLine
   * @internal
   */
  public function onReceiveError($responseLine): void {
    if ($responseLine === NULL || $responseLine === '') {
      return;
    }

    $this->log->warning("STDERR: $responseLine");
  }

  /**
   * Reserve this worker. Set the stub for the pending request
   * ($this->deferred).
   *
   * @return \React\Promise\Deferred
   */
  private function reserveDeferred(): Deferred {
    if ($this->deferred !== NULL) {
      throw new \RuntimeException("Cannot send request. Worker is busy.");
    }

    $this->notIdling();
    $this->deferred = new \React\Promise\Deferred();
    return $this->deferred;
  }

  /**
   * Release this worker. Unset the pending request ($this->deferred).
   *
   * @return \React\Promise\Deferred
   */
  private function releaseDeferred(): Deferred {
    $oldDeferred = $this->deferred;
    $this->deferred = NULL;
    $this->idling();
    return $oldDeferred;
  }

  public function toString() {
    return sprintf('PipeConnection(%s)', $this->id);
  }

  /**
   * @return array
   */
  private function buildEnv(): array {
    $env = getenv();
    if (isset($env['SHELL_VERBOSITY']) && $env['SHELL_VERBOSITY'] > 1) {
      // We often delegate to PHARs, which are often built with `box`, and `box`s
      // super-verbose mode interferes with the protocol.
      $env['SHELL_VERBOSITY'] = 1;
    }
    $env['COWORKER_PIPE'] = 1;
    return $env;
  }

  /**
   * @return \Psr\Log\LoggerInterface
   */
  public function getLog() {
    return $this->log;
  }

}
