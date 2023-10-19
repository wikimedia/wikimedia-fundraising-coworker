<?php

define('QUEUE_EXAMPLE_PREFIX', 'queue_example_');

/**
 * Remove everything from the example queues.
 */
function queue_example_reset(): void {
  CRM_Core_DAO::executeQuery('DELETE FROM civicrm_queue WHERE name LIKE %1', [
    1 => [QUEUE_EXAMPLE_PREFIX . '%', 'String'],
  ]);
  CRM_Core_DAO::executeQuery('DELETE FROM civicrm_queue_item WHERE queue_name LIKE %1', [
    1 => [QUEUE_EXAMPLE_PREFIX . '%', 'String'],
  ]);
}

/**
 *
 * @param string $name
 *   Name of the example queue.
 * @param array $logValues
 *   List of values to put in.
 * @param array|NULL $runAs
 *   (Optional) The contact/domain which runs the tasks
 *   See `CRM_Queue_Task::$runAs`.
 *   Ex: ['contactId' => 123, 'domainId' => 1]
 */
function queue_example_fill(string $name, array $logValues, $runAs = NULL): void {
  $specs = [];
  $specs['old'] = [
    'type' => 'SqlParallel',
    'runner' => 'task', /* Works on 5.68+ but emits deprecation notice... so nicer to use the other form... */
    'error' => 'delete',
  ];
  $specs['new'] = [
    'type' => 'SqlParallel',
    'agent' => 'server',
    'payload' => 'task',
    'error' => 'delete',
  ];

  /** @var CRM_Queue_Queue $queue */
  $queue = Civi::queue(QUEUE_EXAMPLE_PREFIX . $name,
    version_compare(CRM_Utils_System::version(), '5.68.alpha', '<') ? $specs['old'] : $specs['new']);
  foreach ($logValues as $offset => $logValue) {
    $task = new CRM_Queue_Task('queue_example_logme', [$logValue]);
    $task->runAs = $runAs;
    $queue->createItem($task);
  }
}

function queue_example_logme(CRM_Queue_TaskContext $ctx, $logValue): bool {
  $log = getenv('QUEUE_EXAMPLE_LOG');
  if (!$log || !file_exists($log) || !is_writable($log)) {
    throw new Exception('Undefined variable: QUEUE_EXAMPLE_LOG');
  }
  $msg = json_encode([
    't' => time(),
    'v' => $logValue,
    'u' => CRM_Core_Session::getLoggedInContactID(),
    'd' => CRM_Core_BAO_Domain::getDomain()->id,
  ]) . "\n";
  file_put_contents($log, $msg, FILE_APPEND);
  return TRUE;
}
