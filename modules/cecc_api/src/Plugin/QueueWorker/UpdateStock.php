<?php

namespace Drupal\cecc_api\Plugin\QueueWorker;

/**
 * Processes tasks for Data Source queue.
 *
 * @QueueWorker(
 *   id = "po_update_stock",
 *   title = @Translation("Queue worker for updating a single publication stock."),
 *   cron = {"time" = 90}
 * )
 */
class UpdateStock extends UpdateStockQueueWorkerBase {}
