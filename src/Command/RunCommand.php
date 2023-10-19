<?php

namespace Civi\Coworker\Command;

use Civi\Coworker\Client\CiviClientInterface;
use Civi\Coworker\Client\CiviPipeClient;
use Civi\Coworker\CiviQueueWatcher;
use Civi\Coworker\PipeConnection;
use Civi\Coworker\PipePool;
use Civi\Coworker\Runtime;
use Civi\Coworker\Util\PromiseUtil;
use Civi\Coworker\Util\TaskSplitter;
use React\EventLoop\Loop;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function Clue\React\Block\await;

class RunCommand extends Command {

  use ConfigurationTrait;

  protected function configure() {
    $this
      ->setName('run')
      ->setDescription('Monitor queue for tasks and execute them.')
      ->addOption('channel', NULL, InputOption::VALUE_REQUIRED, 'Preferred communication channel (web,pipe). May give multiple for hybrid communication.')
      ->addOption('web', NULL, InputOption::VALUE_REQUIRED, 'Connect via web URL (HTTP base URL)')
      ->setHelp(
        "Monitor queue for tasks and execute them.\n" .
        "\n" .
        "<comment>Examples: Web (HTTPS):</comment>\n" .
        "\n" .
        "  coworker run --web='https://user:pass@example.com/civicrm/queue'\n" .
        "\n" .
        "<comment>Examples: Pipe (Shell/SSH/etc):</comment>\n" .
        "  coworker run\n" .
        "  coworker run --pipe='cv ev \"Civi::pipe();\"'\n" .
        "  coworker run --pipe='drush ev \"civicrm_initialize(); Civi::pipe();\"' \n" .
        "  coworker run --pipe='wp eval \"civicrm_initialize(); Civi::pipe();\"'\n" .
        //  "\n" .
        //  "<comment>Examples: Hybrid (HTTPS+SSH):</comment>\n" .
        //  "  coworker run --channel=web,pipe \\\n" .
        //  "    --web='https://user:pass@example.com/civicrm/queue' \\\n" .
        //  "    --pipe='ssh worker@example.com cv ev \"Civi::pipe();\"'\n" .
        "\n"
      );
    $this->configureCommonOptions();
    parent::configure();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $runtime = Runtime::get();
    $runtime->io = new SymfonyStyle($input, $output);
    $runtime->input = $input;
    $runtime->output = $output;
    $runtime->config = $this->createConfiguration($input, $output);
    $runtime->logger = $this->createLogger($input, $output, $runtime->config);

    $runtime->logger->debug('Configuration: {c}', ['c' => (array) $runtime->config]);

    // [$ctlChannel, $workChannel] = $this->pickChannels($input, $output);
    // $logger->info('Setup channels (control={ctl}, work={work})', [
    //   'ctl' => $ctlChannel,
    //   'work' => $workChannel,
    // ]);

    $ctl = $this->createControlChannel($runtime);
    $welcome = await($ctl->start());

    if (($welcome['t'] ?? NULL) === 'trusted') {
      await($ctl->options(['apiCheckPermissions' => FALSE]));
    }
    else {
      throw new \Exception('coworker requires a trusted control channel');
      // Future: Alternatively, if $header['l']==='login' and you have login-credentials,
      // then perform a login.
    }

    $workerPool = $this->createWorkerPool($runtime);
    await($workerPool->start());

    $watcher = new CiviQueueWatcher($runtime->config(), $ctl, $workerPool, $runtime->logger()->withName('CiviQueueWatcher'));
    $onStop = PromiseUtil::on($watcher, 'stop');

    $watcher->start()->then(function() use ($runtime, $watcher) {
      $config = $runtime->config();
      if (!empty($config->maxTotalDuration)) {
        Loop::addTimer($config->maxTotalDuration, function() use ($watcher) {
          Runtime::get()->logger()->info('Exceeded duration limit ({sec} seconds).', [
            'sec' => Runtime::get()->config()->maxTotalDuration,
          ]);
          $watcher->stop()->then(function() {
            Runtime::get()->logger()->info('Stopped');
          });
        });
      }
    });

    await($onStop);
  }

  protected function pickChannels(InputInterface $input, OutputInterface $output): array {
    if (!$input->getOption('channel')) {
      if ($input->getOption('pipe') && !$input->getOption('web')) {
        return ['pipe', 'pipe'];
      }
      elseif ($input->getOption('web') && !$input->getOption('pipe')) {
        return ['web', 'web'];
      }
    }

    switch ($input->getOption('channel')) {
      case 'web':
        return ['web', 'web'];

      case 'pipe':
        return ['pipe', 'pipe'];

      case 'pipe,web':
        return ['pipe', 'web'];

      case 'web,pipe':
        return ['web', 'pipe'];

      default:
        throw new \RuntimeException("Please set --channel options");
    }
  }

  /**
   * @param \Civi\Coworker\Runtime $runtime
   * @return \Civi\Coworker\Client\CiviClientInterface
   */
  protected function createControlChannel(Runtime $runtime): CiviClientInterface {
    $pipeConnection = new PipeConnection(
      $runtime->config(),
      'ctl',
      $runtime->logger()->withName('CtlPipe')
    );
    return new CiviPipeClient(
      $runtime->config(),
      $pipeConnection,
      $runtime->logger()->withName('CtlClient')
    );
  }

  /**
   * @param \Civi\Coworker\Runtime $runtime
   * @return \Civi\Coworker\PipePool
   *   FIXME: Don't specifically require pipes!
   */
  protected function createWorkerPool(Runtime $runtime): PipePool {
    return new PipePool(
      $runtime->config(),
      $runtime->logger()->withName('Pool'),
      [TaskSplitter::class, 'onConnect']
    );
  }

}
