<?php

namespace Drupal\cecc_restocked\Plugin\QueueWorker;

/**
 * Processes tasks for Data Source queue.
 *
 * @QueueWorker(
 *   id = "cecc_restock_notification",
 *   title = @Translation("Queue worker for sending a single restock notification."),
 *   cron = {"time" = 30}
 * )
 */
class RestockNotification extends RestockNotificationQueueWorkerBase {}
