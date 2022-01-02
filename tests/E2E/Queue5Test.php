<?php

namespace Civi\Citges\E2E;

use Civi\Citges\CitgesTestTrait;
use PHPUnit\Framework\TestCase;

/**
 * @group e2e
 */
class Queue5Test extends TestCase {

  use CitgesTestTrait;

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
    $this->cvEval('queue_example_reset("qx");');
    $this->cvEval('queue_example_addlogme("qx", range(0,5));');
    $this->execute('run', [
      '--pipe' => $this->cvCmd('ev "Civi::pipe();"'),
    ]);
    $lines = $this->parseJsonLines($this->logFile);
    $this->assertCount(5, $lines);
  }

  protected function parseJsonLines(string $file): array {
    $content = rtrim(file_get_contents($file), "\r\n");
    $lines = explode("\n", $content);
    return ($content === '') ? [] : array_map(
      function($line) {
        return \json_decode($line, 1);
      },
      $lines
    );
  }

}