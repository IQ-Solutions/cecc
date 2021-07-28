<?php

namespace Drupal\cecc_api\Plugin\QueueWorker;

/**
 * Processes tasks for Data Source queue.
 *
 * @QueueWorker(
 *   id = "cecc_update_stock",
 *   title = @Translation("Queue worker for updating a single publication stock."),
 *   cron = {"time" = 90}
 * )
 */
class UpdateStock extends UpdateStockQueueWorkerBase {}
