<?php

namespace \Drupal\cecc_restocked\EventSubscriber;

use Drupal\cecc_stock\Service\StockValidation;
use Drupal\commerce_product\Event\ProductEvents;
use Drupal\commerce_product\Event\ProductVariationEvent;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles product variation restock updates.
 */
class ProductVariation implements EventSubscriberInterface {

  /**
   * Drupal logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Stock Validation Service.
   *
   * @var \Drupal\cecc_stock\Service\StockValidation
   */
  protected $stockValidation;

  /**
   * Contstructs a new order event subscriber.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Logger Channel Factory.
   * @param \Drupal\cecc_stock\Service\StockValidation $stockValidation
   *   Logger Channel Factory.
   */
  public function __construct(
    LoggerChannelFactoryInterface $loggerFactory,
    StockValidation $stockValidation
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $loggerFactory->get('cecc');
    $this->stockValidation = $stockValidation;
  }

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      ProductEvents::PRODUCT_VARIATION_UPDATE => ['onProductVariationUpdate', -100],
    ];

    return $events;
  }

  public function onProductVariationUpdate(ProductVariationEvent $event) {
    $productVariation = $event->getProductVariation();
    $inStock = $this->stockValidation->checkProductStock($productVariation);

    if ($inStock) {
      /** @todo Create Queuing service to send emails */
    }

  }

}
