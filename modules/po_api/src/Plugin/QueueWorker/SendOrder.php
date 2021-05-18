<?php

namespace Drupal\po_api\Plugin\QueueWorker;

/**
 * Processes api task for the order queue.
 *
 * @QueueWorker(
 *   id = "po_send_order",
 *   title = @Translation("Queue worker for sending orders."),
 *   cron = {"time" = 90}
 * )
 */
class SendOrder extends SendOrderQueueWorkerBase {}
