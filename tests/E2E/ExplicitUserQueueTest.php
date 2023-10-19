<?php

namespace Civi\Coworker\E2E;

use Civi\Coworker\CoworkerTestTrait;
use PHPUnit\Framework\TestCase;

/**
 * @group e2e
 */
class ExplicitUserQueueTest extends TestCase {

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
    $adminCid = $this->findUserCid('admin');

    $this->cvEval('queue_example_reset();');
    $this->cvEval('queue_example_fill("a", range(1,4), ["domainId"=>1,"contactId"=>' . $adminCid . ']);');

    $this->execute('run', [
      '--pipe' => $this->cvCmd('ev "Civi::pipe();"'),
      '--define' => ['agentDuration=10'],
    ]);
    $this->assertExampleJsonOutput($this->logFile, [
      ['v' => 1, 'u' => $adminCid, 'd' => 1],
      ['v' => 2, 'u' => $adminCid, 'd' => 1],
      ['v' => 3, 'u' => $adminCid, 'd' => 1],
      ['v' => 4, 'u' => $adminCid, 'd' => 1],
    ]);
  }

}
