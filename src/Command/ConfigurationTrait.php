<?php

namespace Civi\Coworker\Command;

use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Civi\Coworker\Configuration;
use Civi\Coworker\Util\LogFilter;
use Civi\Coworker\Util\MiniPipeUtil;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

trait ConfigurationTrait {

  public function configureCommonOptions() {
    $this->addOption('config', 'c', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Load a configuration file');
    $this->addOption('define', 'd', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Define a config option (KEY=VALUE)', []);
    $this->addOption('log', NULL, InputOption::VALUE_REQUIRED, 'Log file');
    $this->addOption('pipe', NULL, InputOption::VALUE_REQUIRED, 'Connect via pipe (launcher command)');
  }

  protected function createConfiguration(InputInterface $input, OutputInterface $output): Configuration {
    // Wouldn't it be nicer to have some attribute/annotation mapping...

    $optionMap = [
      'pipe' => 'pipeCommand',
      'log' => 'logFile',
    ];
    $envMapDepr = [
      'COWORKER_MAX_DURATION' => 'COWORKER_AGENT_DURATION',
      'COWORKER_MAX_WORKERS' => 'COWORKER_COUNT',
      'COWORKER_WORKER_REQUESTS' => 'COWORKER_REQUESTS',
      'COWORKER_WORKER_DURATION' => 'COWORKER_DURATION',
      'COWORKER_WORKER_IDLE' => 'COWORKER_TIMEOUT',
    ];
    $envMap = [
      // Convention: "workerFoo" => "COWORKER_FOO"
      'COWORKER_COUNT' => 'workerCount',
      'COWORKER_REQUESTS' => 'workerRequests',
      'COWORKER_DURATION' => 'workerDuration',
      'COWORKER_TIMEOUT' => 'workerTimeout',
      'COWORKER_GC_WORKERS' => 'workerCleanupCount',

      // For all others: "fooBar" => "COWORKER_FOO_BAR"
      'COWORKER_AGENT_DURATION' => 'agentDuration',
      'COWORKER_POLL_INTERVAL' => 'pollInterval',
    ];

    $cfg = new Configuration();

    foreach ($input->getOption('config') as $configFile) {
      if (!file_exists($configFile)) {
        continue;
      }

      if (preg_match(';\.json$;', $configFile)) {
        $parse = json_decode(file_get_contents($configFile), TRUE);
        if (!is_array($parse)) {
          throw new \RuntimeException("Malformed configuration file: $configFile");
        }
        $cfg->loadOptions($parse);
      }
      elseif (preg_match(';\.(yaml|yml)$;', $configFile)) {
        $parse = Yaml::parseFile($configFile);
        if (!is_array($parse)) {
          throw new \RuntimeException("Malformed configuration file: $configFile");
        }
        $cfg->loadOptions($parse);
      }
      else {
        $output->writeln("<error>Skipped unrecognized config file: $configFile</error>");
      }
    }

    foreach ($envMapDepr as $envVarOld => $envVarNew) {
      $envValue = getenv($envVarOld);
      if ($envValue !== FALSE) {
        error_log(sprintf('DEPRECATED: Environment variable "%s" renamed to "%s"', $envVarOld, $envVarNew));
        $cfg->{$envMap[$envVarNew]} = $envValue;
      }
    }
    foreach ($envMap as $envVar => $cfgOption) {
      $envValue = getenv($envVar);
      if ($envValue !== FALSE) {
        $cfg->{$cfgOption} = $envValue;
      }
    }

    if (empty($input->getOption('pipe')) && empty($input->getOption('web')) && empty($cfg->pipeCommand)) {
      $cfg->pipeCommand = 'MINIPIPE';
    }

    foreach ($optionMap as $inputOption => $cfgOption) {
      $inputValue = $input->getOption($inputOption);
      if ($inputValue !== '' && $inputValue !== NULL) {
        $cfg->{$cfgOption} = $inputValue;
      }
    }

    foreach ($input->getOption('define') as $configOptionValue) {
      [$cfgOption, $inputValue] = explode('=', $configOptionValue, 2);
      $cfg->{$cfgOption} = $inputValue;
    }

    if (empty($cfg->logLevel)) {
      if ($output->isVeryVerbose()) {
        $cfg->logLevel = 'debug';
      }
      elseif ($output->isVerbose()) {
        $cfg->logLevel = 'info';
      }
      else {
        $cfg->logLevel = 'notice';
      }
    }

    if (preg_match('/^MINIPIPE( ([A-Za-z0-9]*))?$/', $cfg->pipeCommand, $m)) {
      $cfg->pipeCommand = MiniPipeUtil::createCommand($m[2] ?? NULL);
    }
    elseif (preg_match('/^MINIPIPE/', $cfg->pipeCommand)) {
      throw new \RuntimeException("Malformed pipe command: $cfg->pipeCommand");
    }

    return $cfg;
  }

  /**
   * @return \Monolog\Logger
   */
  protected function createLogger(InputInterface $input, OutputInterface $output, Configuration $config): Logger {
    $log = new \Monolog\Logger($this->getName());

    if ($config->logFile) {
      $fileHandler = new StreamHandler($config->logFile);
      $fileHandler->setFormatter($config->logFormat === 'json' ? new JsonFormatter() : new LineFormatter());
      $log->pushHandler(new LogFilter($config, $fileHandler));
    }

    if ($output->isVerbose() || !$config->logFile) {
      $consoleHandler = new class($output) extends AbstractProcessingHandler {

        /**
         * @var \Symfony\Component\Console\Output\OutputInterface
         */
        protected $output;

        public function __construct(OutputInterface $output, $level = Logger::DEBUG, bool $bubble = TRUE) {
          parent::__construct($level, $bubble);
          $this->output = $output;
        }

        protected function write(array $record): void {
          $this->output->write($record['formatted']);
        }

      };

      if ($config->logFormat === 'json') {
        $consoleFormatter = new JsonFormatter();
      }
      else {
        $consoleFormatter = new ColoredLineFormatter();
        $consoleFormatter->ignoreEmptyContextAndExtra();
        $consoleFormatter->allowInlineLineBreaks();
      }

      // $consoleHandler = new StreamHandler(STDERR, $config->logLevel);
      $consoleHandler->setFormatter($consoleFormatter);
      $log->pushHandler(new LogFilter($config, $consoleHandler));
    }

    $log->pushProcessor(new \Monolog\Processor\PsrLogMessageProcessor());
    return $log;
  }

}
