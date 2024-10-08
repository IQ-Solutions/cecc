<?php

namespace Drupal\cecc_api\Plugin\QueueWorker;

/**
 * Processes api task for the order queue.
 *
 * @QueueWorker(
 *   id = "cecc_send_order",
 *   title = @Translation("Queue worker for sending orders (CECC)."),
 *   cron = {"time" = 90}
 * )
 */
class SendOrder extends SendOrderQueueWorkerBase {}
