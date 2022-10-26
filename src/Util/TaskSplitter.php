<?php

namespace Civi\Coworker\Util;

class TaskSplitter {

  /**
   * Split a batch of $items into contiguous sub-batches.
   *
   * Background:
   *
   * - When you `claimItems()` on a queue that supports batching, you may get multiple items
   *   in one response.
   * - Items can be assigned to different identities (depending on their inline data).
   * - If tasks have different identities, then they must run separately.
   * - Therefore, it is conceivable that you get a batch with a mix of identities,
   *   and they should be re-split into smaller batches.
   * - Depending on the type of queue, ordering of items may be important.
   * - However, that should be atypical. In the average case, you would expect one batch to
   *   have similar/consistent identities.
   *
   * At time writing, I regard the "typical" and "atypical" use-cases as follows:
   *
   * - (Typical) A batched-queue has all items running with the same identity
   * - (Typical) A batched-queue has contiguous clusters of items with the same identity.
   * - (Atypical) A batched-queue has items with interleaved/alternating identities
   * - (Typical) A basic (non-batched) queue has diverse task-items (any mix of identities).
   *
   * The algorithm here splits the batch into sub-batches.
   *
   * - Each sub-batch uses exactly one identity. Identities are NEVER mixed.
   * - If two adjacent tasks use the same identity, then they will be in the same sub-batch.
   * - Within and across sub-batches, the relative ordering is preserved.
   *
   * This should be secure for all typical+atypical cases, but it may be suboptimal for
   * some atypical cases.
   *
   * @param array $items
   *   List of specific queue items.
   *   These items correspond to `Queue.claimItems` API. They should include `id` and `run_as`.
   *   Ex: [['id' => 123, 'run_as' => ['contactId' => 100,'domainId'=>1]]]
   * @return array
   *   The list of queue items, split out as various sub-batches.
   *   Each sub-batch specifies a `context` name and some `items` to work on.
   *   Ex: [['context' => 'c=100,d=1', 'items' => [...]]]
   */
  public static function split(array $items): array {
    $subGroups = [];
    $nextGroup = [];
    $nextContext = NULL;
    $acceptNext = function() use (&$subGroups, &$nextContext, &$nextGroup) {
      if ($nextGroup) {
        $subGroups[] = ['context' => $nextContext, 'items' => $nextGroup];
      }
      $nextContext = NULL;
      $nextGroup = [];
    };

    foreach ($items as $item) {
      $context = self::encodeContextName($item['run_as']);
      if ($nextContext !== NULL && $nextContext !== $context) {
        $acceptNext();
      }
      $nextContext = $context;
      $nextGroup[] = $item;
    }
    $acceptNext();

    return $subGroups;
  }

  /**
   * @param array $runAs
   *   Ex: ['contactId' => 100, 'domainId' => 1']
   * @return string
   *   Ex: 'd1-c100'
   */
  public static function encodeContextName($runAs): string {
    return sprintf('d%d-c%d', $runAs['domainId'] ?? 'null', $runAs['contactId'] ?? 'null');
  }

  /**
   * @param string $context
   *   Ex: 'd1-c100'
   * @return array
   *   Ex: ['contactId' => 100, 'domainId' => 1']
   */
  public static function decodeContextName(string $context): array {
    if (preg_match('/^d(\d+)-c(\d+)$/', $context, $m)) {
      return [
        'contactId' => $m[2] === '0' ? NULL : $m[2],
        'domainId' => $m[1] === '0' ? NULL : $m[1],
      ];
    }
  }

}
