<?php

namespace Drupal\cecc_api\EventSubscriber;

use Drupal\cecc_stock\Event\LowStockEvent;
use Drupal\cecc_stock\Event\OutStockEvent;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles the out of stock event.
 */
class Stock implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The Queue service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Drupal logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Contstructs a new order event subscriber.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Logger Channel Factory.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   Queue Factory service.
   */
  public function __construct(
    LoggerChannelFactoryInterface $loggerFactory,
    QueueFactory $queueFactory
  ) {
    $this->logger = $loggerFactory->get('cecc_api');
    $this->queueFactory = $queueFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      OutStockEvent::CECC_PRODUCT_VARIATION_OUT_STOCK => [
        'onOutOfStock',
        -50,
      ],
      LowStockEvent::CECC_PRODUCT_VARIATION_LOW_STOCK => [
        'onLowStock',
        -50,
      ],
    ];

    return $events;
  }

  public function onOutOfStock(OutStockEvent $event) {
    $productVariation = $event->productVariation;

    $item = [
      'id' => $productVariation->id(),
      'sku' => $productVariation->get('sku')->value,
      'warehouse_item_id' => $productVariation->get('field_cecc_warehouse_item_id')->value,
    ];

    $queue = $this->queueFactory->get('cecc_update_stock');
    $queue->createItem($item);

    $this->logger->notice($this->t('Stock for %label is out of stock. It has been queued for a stock refresh.', [
      '%label' => $productVariation->getTitle(),
    ]));

  }

  public function onLowStock(OutStockEvent $event) {
    $productVariation = $event->productVariation;

    $item = [
      'id' => $productVariation->id(),
      'sku' => $productVariation->get('sku')->value,
      'warehouse_item_id' => $productVariation->get('field_cecc_warehouse_item_id')->value,
    ];

    $queue = $this->queueFactory->get('cecc_update_stock');
    $queue->createItem($item);

    $this->logger->notice($this->t('Stock for %label has been fallen below %stockLevel. It has been queued for a stock refresh.', [
      '%label' => $productVariation->getTitle(),
      '%stockLevel' => $productVariation->get('cecc_check_stock_threshold')->value,
    ]));

  }

}
