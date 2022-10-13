<?php

namespace Civi\Coworker;

use Monolog\Logger;
use Symfony\Component\Console\Style\StyleInterface;

class Runtime {

  /**
   * @var \Symfony\Component\Console\Style\StyleInterface
   */
  public $io;

  /**
   * @var \Symfony\Component\Console\Input\InputInterface
   */
  public $input;

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  public $output;

  /**
   * @var \Civi\Coworker\Configuration
   */
  public $config;

  /**
   * @var \Monolog\Logger
   */
  public $logger;

  public static function get(): Runtime {
    static $singleton;
    if ($singleton === NULL) {
      $singleton = new Runtime();
    }
    return $singleton;
  }

  public function io(): StyleInterface {
    return $this->io;
  }

  public function config(): Configuration {
    return $this->config;
  }

  public function logger(): Logger {
    return $this->logger;
  }

}
