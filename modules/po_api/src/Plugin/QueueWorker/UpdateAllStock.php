<?php

namespace Drupal\po_api\Plugin\QueueWorker;

/**
 * Processes tasks for Data Source queue.
 *
 * @QueueWorker(
 *   id = "po_update_all_stock",
 *   title = @Translation("Queue worker for updating publication stock."),
 *   cron = {"time" = 90}
 * )
 */
class UpdateAllStock extends UpdateStockQueueWorkerBase {}
