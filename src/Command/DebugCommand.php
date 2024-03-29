<?php

namespace Civi\Coworker\Command;

use Civi\Coworker\Configuration;
use Civi\Coworker\PipePool;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DebugCommand extends RunCommand {

  protected function configure() {
    parent::configure();
    $this->setName('debug');
    $this->setDescription($this->getDescription() . ' (debug mode)');
  }

  protected function createConfiguration(InputInterface $input, OutputInterface $output): Configuration {
    $config = parent::createConfiguration($input, $output);

    // Don't make me think about parallel workers.
    // It's good to restart after each request - because it ensures that recently edited PHP files will be reloaded.

    $config->logLevel = 'debug';
    $config->workerCount = 1;
    $config->workerTimeout = PipePool::QUEUE_INTERVAL / 2;
    $config->workerRequests = 1;
    $config->workerCleanupCount = 1;

    return $config;
  }

}
