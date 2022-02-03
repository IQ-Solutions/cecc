<?php

namespace Drupal\cecc_stock\EventSubscriber;

use Drupal\cecc_stock\Event\LowStockEvent;
use Drupal\cecc_stock\Event\OutStockEvent;
use Drupal\cecc_stock\Event\RestockEvent;
use Drupal\cecc_stock\Service\StockHelper;
use Drupal\cecc_stock\Service\StockValidation;
use Drupal\commerce_product\Event\ProductEvents;
use Drupal\commerce_product\Event\ProductVariationEvent;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Stock the state service.
   *
   * @var \Drupal\core\State\StateInterface
   */
  protected $state;

  /**
   * Contstructs a new order event subscriber.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Logger Channel Factory.
   * @param \Drupal\cecc_stock\Service\StockValidation $stockValidation
   *   Logger Channel Factory.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher service.
   * @param \Drupal\core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(
    LoggerChannelFactoryInterface $loggerFactory,
    StockValidation $stockValidation,
    EventDispatcherInterface $eventDispatcher,
    StateInterface $state
  ) {
    $this->logger = $loggerFactory->get('cecc');
    $this->stockValidation = $stockValidation;
    $this->eventDispatcher = $eventDispatcher;
    $this->state = $state;
  }

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      ProductEvents::PRODUCT_VARIATION_UPDATE => [
        'onProductVariationUpdate',
        -100,
      ],
      OutStockEvent::CECC_PRODUCT_VARIATION_OUT_STOCK => [
        'onOutOfStock',
        -50,
      ],
      LowStockEvent::CECC_PRODUCT_VARIATION_LOW_STOCK => [
        'onLowStock',
        -50,
      ],
      RestockEvent::CECC_PRODUCT_VARIATION_RESTOCK => [
        'onRestock',
        -100,
      ],
    ];

    return $events;
  }

  /**
   * Fires when a product variation is updated.
   *
   * @param Drupal\commerce_product\Event\ProductVariationEvent $event
   *   The event.
   */
  public function onProductVariationUpdate(ProductVariationEvent $event) {
    $productVariation = $event->getProductVariation();
    $inStock = $this->stockValidation->checkProductStock($productVariation);
    $lowStock = $this->stockValidation->isStockBelowThreshold($productVariation);
    $stateId = StockHelper::PVS_PREFIX . $productVariation->id();
    $productVariationStockState = $this->state->get($stateId);

    if (!$inStock && is_null($productVariationStockState)) {
      $outStockEvent = new OutStockEvent($productVariation);
      $this->eventDispatcher->dispatch($outStockEvent, OutStockEvent::CECC_PRODUCT_VARIATION_OUT_STOCK);
    }
    elseif ($lowStock && is_null($productVariationStockState)) {
      $lowStockEvent = new LowStockEvent($productVariation);
      $this->eventDispatcher->dispatch($lowStockEvent, LowStockEvent::CECC_PRODUCT_VARIATION_LOW_STOCK);
    }

  }

  /**
   * Fires when a product variation is low stock.
   *
   * @param Drupal\cecc_stock\Event\LowStockEvent $event
   *   The event.
   */
  public function onLowStock(LowStockEvent $event) {
    $productVariation = $event->productVariation;
    $stateId = StockHelper::PVS_PREFIX . $productVariation->id();

    $this->state->set($stateId, (int) $productVariation->field_cecc_stock->value);
  }

  /**
   * Fires when a product variation is out of stock.
   *
   * @param Drupal\cecc_stock\Event\OutStockEvent $event
   *   The event.
   */
  public function onOutOfStock(OutStockEvent $event) {
    $productVariation = $event->productVariation;
    $stateId = StockHelper::PVS_PREFIX . $productVariation->id();

    $this->state->set($stateId, (int) $productVariation->field_cecc_stock->value);
  }

  /**
   * Fires when a product variation is out of stock.
   *
   * @param Drupal\cecc_stock\Event\RestockEvent $event
   *   The event.
   */
  public function onRestock(RestockEvent $event) {
    $productVariation = $event->productVariation;
    $stateId = StockHelper::PVS_PREFIX . $productVariation->id();
    $productVariationStockState = $this->state->get($stateId);

    if ($productVariationStockState < $productVariation->field_cecc_stock->value) {
      $this->state->delete($stateId);
    }
  }

}
