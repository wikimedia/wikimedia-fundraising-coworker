<?php

define('QUEUE_EXAMPLE_PREFIX', 'queue_example_');

function queue_example_reset(): void {
  CRM_Core_DAO::executeQuery('DELETE FROM civicrm_queue WHERE name LIKE %1', [
    1 => [QUEUE_EXAMPLE_PREFIX . '%', 'String'],
  ]);
  CRM_Core_DAO::executeQuery('DELETE FROM civicrm_queue_item WHERE queue_name LIKE %1', [
    1 => [QUEUE_EXAMPLE_PREFIX . '%', 'String'],
  ]);
}

function queue_example_fill(string $name, array $logValues): void {
  /** @var CRM_Queue_Queue $queue */
  $queue = Civi::queue(QUEUE_EXAMPLE_PREFIX . $name, [
    'type' => 'SqlParallel',
    'runner' => 'task',
  ]);
  foreach ($logValues as $logValue) {
    $queue->createItem(new CRM_Queue_Task('queue_example_logme', [$logValue]));
  }
}

function queue_example_logme(CRM_Queue_TaskContext $ctx, $logValue): bool {
  $log = getenv('QUEUE_EXAMPLE_LOG');
  if (!$log || !file_exists($log) || !is_writable($log)) {
    throw new Exception('Undefined variable: QUEUE_EXAMPLE_LOG');
  }
  $msg = json_encode(['t' => time(), 'v' => $logValue]) . "\n";
  file_put_contents($log, $msg, FILE_APPEND);
  return TRUE;
}
