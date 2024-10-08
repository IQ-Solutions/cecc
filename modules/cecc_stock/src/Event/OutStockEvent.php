<?php

namespace Drupal\cecc_stock\Event;

use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Component\EventDispatcher\Event;

/**
 * Event thats fires when a product is out of stock.
 */
class OutStockEvent extends Event {
  const CECC_PRODUCT_VARIATION_OUT_STOCK = 'cecc_stock_out_stock';

  /**
   * The product variation being restocked.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariation
   */
  public $productVariation;

  /**
   * Constructs the object.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariation $productVariation
   *   The product variation.
   */
  public function __construct(ProductVariation $productVariation) {
    $this->productVariation = $productVariation;
  }

}
