<?php
namespace Civi\Coworker;

use Civi\Coworker\Util\JsonLines;
use React\Promise\PromiseInterface;
use Symfony\Component\Console\Tester\CommandTester;

trait CoworkerTestTrait {

  protected function setupE2E() {
    $copyIfNew = function($from, $to) {
      if (!file_exists($to) || filemtime($from) > filemtime($to)) {
        copy($from, $to);
      }
    };

    $extDir = $this->cv('path -c extensionsDir');
    $myExtDir = "$extDir/queue-example";
    if (!is_dir($myExtDir)) {
      mkdir($myExtDir);
    }
    $copyIfNew(__DIR__ . '/queue-example/info.xml', "$myExtDir/info.xml");
    $copyIfNew(__DIR__ . '/queue-example/queue_example.php', "$myExtDir/queue_example.php");

    $this->cv('en authx queue_example');
  }

  /**
   * Create a helper for executing command-tests in our application.
   *
   * @param string $commandName
   * @param array $args must include key "command"
   * @return \Symfony\Component\Console\Tester\CommandTester
   */
  public function execute(string $commandName, array $args = []): \Symfony\Component\Console\Tester\CommandTester {
    $args = array_merge([
      'command' => $commandName,
      '--define' => [],
      '--verbose' => TRUE,
    ], $args);
    array_unshift($args['--define'], 'logLevel=debug');
    array_unshift($args['--define'], 'logFormat=json');
    $application = new Application();
    $command = $application->find($args['command']);
    $commandTester = new CommandTester($command);
    $commandTester->execute($args);
    return $commandTester;
  }

  public function getPath(?string $relFile = NULL): string {
    $dir = dirname(__DIR__);
    return $relFile ? "$dir/$relFile" : $dir;
  }

  protected function await(PromiseInterface $promise) {
    return \Clue\React\Block\await($promise, NULL, 120);
  }

  protected function cv(string $cmd): string {
    $cvCmd = $this->cvCmd($cmd);
    $result = system($cvCmd, $exitCode);
    if ($exitCode !== 0) {
      throw new \RuntimeException("Command failed: $cvCmd\n$result\n");
    }
    return $result;
  }

  protected function cvEval(string $phpCode, ?string $user = NULL): string {
    $cmd = 'ev ';
    if ($user !== NULL) {
      $cmd .= '--user=' . escapeshellarg($user) . ' ';
    }
    $cmd .= escapeshellarg($phpCode);
    return $this->cv($cmd);
  }

  /**
   * @param $cmd
   *
   * @return string
   */
  protected function cvCmd(string $cmd): string {
    $cvRoot = getenv('CV_TEST_BUILD');
    $this->assertTrue(is_dir($cvRoot), "CV_TEST_BUILD ($cvRoot) should be a valid root");
    $cvCmd = sprintf('cv --cwd=%s %s', escapeshellarg($cvRoot), $cmd);
    return $cvCmd;
  }

  protected function findUserCid(string $username): int {
    $val = trim($this->cvEval('echo CRM_Core_Session::getLoggedInContactID();', $username));
    if ($val && is_numeric($val)) {
      return (int) $val;
    }
    else {
      throw new \RuntimeException("Expected contact id for $username. Received: $val");
    }
  }

  /**
   * Assert that the 'queue_example' log file contains certain data.
   *
   * @param string $jsonLogFile
   * @param array $expected
   *   Ex: [0 => ['d' => EXPECT_DATA, 'u' => EXPECT_USER, 'd' => EXPECT_DOMAIN]]
   */
  protected function assertExampleJsonOutput(string $jsonLogFile, array $expected): void {
    $actualLines = JsonLines::parseFile($jsonLogFile);
    $this->assertEquals(count($expected), count($actualLines), "Lines:\n" . json_encode($actualLines, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    foreach (array_keys($actualLines) as $offset) {
      $this->assertEquals($expected[$offset]['d'], $actualLines[$offset]['d'], "Line $offset has unexpected domain ID");
      $this->assertEquals($expected[$offset]['u'], $actualLines[$offset]['u'], "Line $offset has unexpected user ID");
      $this->assertEquals($expected[$offset]['v'], $actualLines[$offset]['v'], "Line $offset has unexpected value");
    }
  }

}
