<?php

namespace Drupal\cecc_api\Plugin\QueueWorker;

/**
 * Processes tasks for Data Source queue.
 *
 * @QueueWorker(
 *   id = "cecc_update_all_stock",
 *   title = @Translation("Queue worker for updating publication stock."),
 *   cron = {"time" = 90}
 * )
 */
class UpdateAllStock extends UpdateStockQueueWorkerBase {}
