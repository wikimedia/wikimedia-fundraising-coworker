<?php

namespace Civi\Coworker\E2E;

use Civi\Coworker\CoworkerTestTrait;
use Civi\Coworker\Util\JsonLines;
use PHPUnit\Framework\TestCase;

/**
 * @group e2e
 */
class Queue5Test extends TestCase {

  use CoworkerTestTrait;

  /**
   * @var string|null
   */
  protected $logFile;

  protected function setUp(): void {
    $this->setupE2E();
    $this->logFile = tempnam(sys_get_temp_dir(), 'queue-example-');
    putenv('QUEUE_EXAMPLE_LOG=' . $this->logFile);
  }

  protected function tearDown(): void {
    parent::tearDown();
    putenv('QUEUE_EXAMPLE_LOG=');
  }

  public function testQueue() {
    $this->cvEval('queue_example_reset();');
    $this->cvEval('queue_example_fill("a", range(1,5));');
    $this->execute('run', [
      '--pipe' => $this->cvCmd('ev "Civi::pipe();"'),
      '--define' => ['maxTotalDuration=10'],
    ]);
    $lines = JsonLines::parseFile($this->logFile);
    $this->assertCount(5, $lines);
  }

}
