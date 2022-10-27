<?php

namespace Civi\Coworker\E2E;

use Civi\Coworker\CoworkerTestTrait;
use PHPUnit\Framework\TestCase;

/**
 * @group e2e
 */
class MultiUserQueueTest extends TestCase {

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
    $demoCid = $this->findUserCid('demo');

    $this->cvEval('queue_example_reset();');
    $this->cvEval('queue_example_fill("a", range(1,3), ["domainId"=>1,"contactId"=>' . $adminCid . ']);');
    $this->cvEval('queue_example_fill("a", range(4,6), ["domainId"=>1,"contactId"=>' . $demoCid . ']);');
    $this->cvEval('queue_example_fill("a", range(7,9), ["domainId"=>1,"contactId"=>' . $adminCid . ']);');

    $this->execute('run', [
      '--pipe' => $this->cvCmd('ev "Civi::pipe();"'),
      '--define' => ['maxTotalDuration=10'],
    ]);
    $this->assertExampleJsonOutput($this->logFile, [
      ['v' => 1, 'u' => $adminCid, 'd' => 1],
      ['v' => 2, 'u' => $adminCid, 'd' => 1],
      ['v' => 3, 'u' => $adminCid, 'd' => 1],
      ['v' => 4, 'u' => $demoCid, 'd' => 1],
      ['v' => 5, 'u' => $demoCid, 'd' => 1],
      ['v' => 6, 'u' => $demoCid, 'd' => 1],
      ['v' => 7, 'u' => $adminCid, 'd' => 1],
      ['v' => 8, 'u' => $adminCid, 'd' => 1],
      ['v' => 9, 'u' => $adminCid, 'd' => 1],
    ]);
  }

}
