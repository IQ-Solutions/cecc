<?php

namespace Drupal\cecc_stock\Service;

use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Stock validation service.
 */
class StockValidation {

  use MessengerTrait;
  use StringTranslationTrait;

  const BELOW_STOCK_LEVEL = 0;
  const ABOVE_STOCK_LEVEL = 1;
  const ORDER_ABOVE_STOCK_LEVEL = 2;
  const ABOVE_ORDER_LIMIT = 1;
  const BELOW_ORDER_LIMIT = 0;

  /**
   * Cart provider service.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal config service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * Service configuration and dependency injection.
   *
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   *   The cart provider service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(
    CartProviderInterface $cart_provider,
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory
  ) {
    $this->cartProvider = $cart_provider;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * Checks if a product is in stock.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $productVariation
   *   The product variation entity.
   * @param int $quantity
   *   The quantity to check against.
   *
   * @return bool
   *   Checks that the stock isn't below threshold and the product is in stock.
   */
  public function checkProductStock(ProductVariationInterface $productVariation, $quantity = 0) {
    $stock_field_name = StockHelper::getStockFieldName($productVariation);
    return !$this->belowStopCheckThreshold($productVariation) &&
      $productVariation->get($stock_field_name)->value > $quantity;
  }

  /**
   * Checks if a product has a stop check threshold and if the stock is below.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $productVariation
   *   The product variation entity.
   *
   * @return bool
   *   If the stop check threshold is greater than 0 check the stock against the
   *   threshold. Defaults to false.
   */
  public function belowStopCheckThreshold(ProductVariationInterface $productVariation) {
    $stock_field_name = StockHelper::getStockFieldName($productVariation);
    $stock_check = StockHelper::getStopCheckThresholdFieldName($productVariation);
    if ($productVariation->get($stock_check)->isEmpty()) {
      return FALSE;
    }

    $stopCheckThreshold = (int) $productVariation->get($stock_check)->value;
    $currentStock = $productVariation->get($stock_field_name)->value;

    return $currentStock < $stopCheckThreshold;
  }

  /**
   * Check order is in stock.
   *
   * @param int $orderId
   *   The order id.
   * @param bool $sendMessage
   *   Send drupal message.
   *
   * @return bool
   *   Returns true if in stock, false if not.
   */
  public function isOrderInStock($orderId, $sendMessage = TRUE) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $this->entityTypeManager->getStorage('commerce_order')->load($orderId);
    $orderInStock = TRUE;

    foreach ($order->getItems() as $orderItem) {
      /** @var \Drupal\commerce\PurchasableEntityInterface $purchasedEntity */
      $purchasedEntity = $orderItem->getPurchasedEntity();

      if (!$purchasedEntity) {
        continue;
      }

      $name = $purchasedEntity->getTitle();
      $qty = $orderItem->getQuantity();
      $stock_field_name = StockHelper::getStockFieldName($purchasedEntity);

      if (!$this->checkProductStock($purchasedEntity, $qty)) {
        if ($sendMessage) {
          $stockMessage = $this->t('The maximum quantity for %name that can be ordered is %qty.', [
            '%name' => $name,
            '%qty' => $purchasedEntity->get($stock_field_name)->value,
          ]);

          $this->messenger()->addError($stockMessage);
        }

        $orderInStock = FALSE;
      }
    }

    return $orderInStock;
  }

  /**
   * Checks stock level below threshold.
   *
   * @param \Drupal\commerce\PurchasableEntityInterface $entity
   *   The product variation.
   *
   * @return bool
   *   Returns TRUE if below threshold, FALSE if above.
   */
  public function isStockBelowThreshold(PurchasableEntityInterface $entity) {
    $stock_field_name = StockHelper::getStockFieldName($entity);
    $stock_check = StockHelper::getStockThresholdFieldName($entity);
    if (!$entity->get($stock_check )->isEmpty()) {
      $stockLevel = $entity->get($stock_field_name)->isEmpty() ? 0 : $entity->get($stock_field_name)->value;
      $stockLevelThreshold = $entity->get($stock_check )->value;

      return $stockLevel <= $stockLevelThreshold;
    }

    return FALSE;
  }

  /**
   * Check if cart quantity is at or above the quantity limit.
   *
   * @param int $variationId
   *   The variation id.
   *
   * @return bool
   *   Returns true if cart quantity is above order limit. False if below.
   */
  public function isCartAtQuantityLimit($variationId) {
    $config = $this->configFactory->get('cecc_stock.settings');

    if ($config->get('hard_limit_order_quantity')) {
      /** @var \Drupal\commerce\PurchasableEntityInterface $purchasedEntity */
      $purchasedEntity = $this->entityTypeManager->getStorage('commerce_product_variation')
        ->load($variationId);
      $order_limit_field = StockHelper::getOrderLimitFieldName($purchasedEntity);

      // Get the maximum orderable quantity.
      $orderLimit = $purchasedEntity->get($order_limit_field)->value;
      $cartQuantity = $this->getOrderedQuantity($purchasedEntity);

      return $orderLimit > 0 ? $cartQuantity >= $orderLimit : FALSE;
    }

    return FALSE;
  }

  /**
   * Check if cart quantity and amount being added is above or below order limit.
   *
   * @param int $variationId
   *   The variation id.
   * @param int $quantity
   *   The quantity.
   *
   * @return bool
   *   Returns true if quantity is above order limit. False if below.
   */
  public function isCartOverQuantityLimit($variationId, $quantity) {
    $config = $this->configFactory->get('cecc_stock.settings');

    if ($config->get('hard_limit_order_quantity')) {
      /** @var \Drupal\commerce\PurchasableEntityInterface $purchasedEntity */
      $purchasedEntity = $this->entityTypeManager->getStorage('commerce_product_variation')
        ->load($variationId);
      $commerceConfig = $this->configFactory->get('cecc.settings');
      $order_limit_field = StockHelper::getOrderLimitFieldName($purchasedEntity);

      // Get the maximum orderable quantity.
      $orderLimit = $purchasedEntity->get($order_limit_field)->value;
      $cartQuantity = $this->getOrderedQuantity($purchasedEntity);
      $totalQuantity = $cartQuantity + $quantity;

      if ($commerceConfig->get('quantity_update_type') == 'cart') {
        $totalQuantity = $quantity;
      }

      return $orderLimit > 0 ? $totalQuantity > $orderLimit : FALSE;
    }

    return FALSE;
  }

  /**
   * Check if quantity is above or below order limit.
   *
   * @param int $variationId
   *   The variation id.
   * @param int $quantity
   *   The quantity.
   *
   * @return bool
   *   Returns true if quantity is above order limit. False if below.
   */
  public function isOverQuantityLimit($variationId, $quantity) {
    /** @var \Drupal\commerce\PurchasableEntityInterface $purchasedEntity */
    $purchasedEntity = $this->entityTypeManager->getStorage('commerce_product_variation')
      ->load($variationId);
    $order_limit_field = StockHelper::getOrderLimitFieldName($purchasedEntity);

    // Get the maximum orderable quantity.
    $orderLimit = $purchasedEntity->get($order_limit_field)->value;

    return $orderLimit > 0 ? $quantity > $orderLimit : FALSE;
  }

  /**
   * Check the product variation stock level.
   *
   * @param int $variationId
   *   The variation id.
   * @param int $quantity
   *   The quantity.
   */
  public function checkStockLevel($variationId, $quantity) {
    /** @var \Drupal\commerce\PurchasableEntityInterface $purchasedEntity */
    $purchasedEntity = $this->entityTypeManager->getStorage('commerce_product_variation')
      ->load($variationId);
    $stock_field_name = StockHelper::getStockFieldName($purchasedEntity);

    // Get the available stock level.
    $stockLevel = $purchasedEntity->get($stock_field_name)->value;

    // Get the already ordered quantity.
    $alreadyOrdered = $this->getOrderedQuantity($purchasedEntity);
    $totalRequested = $alreadyOrdered + $quantity;

    if ($totalRequested <= $stockLevel) {
      return self::BELOW_STOCK_LEVEL;
    }

    if ($alreadyOrdered === 0) {
      return self::ABOVE_STOCK_LEVEL;
    }
    else {
      return self::ORDER_ABOVE_STOCK_LEVEL;
    }
  }

  /**
   * Get the quantity already ordered for the specified PurchasableEntity.
   *
   * @param \Drupal\commerce\PurchasableEntityInterface $purchasedEntity
   *   The purchasable entity.
   *
   * @return int
   *   The ordered quantity.
   */
  public function getOrderedQuantity(PurchasableEntityInterface $purchasedEntity) {
    // Get the already ordered quantity.
    $alreadyOrdered = 0;
    // Get all the carts.
    $allCarts = $this->cartProvider->getCarts();
    // Cycle all the carts to get the total stock already ordered.
    // It is unlikely that a product will be in more then one cart, but it is
    // probably safer to check.
    foreach ($allCarts as $cart) {
      foreach ($cart->getItems() as $orderItem) {
        $orderPurchasedEntity = $orderItem->getPurchasedEntity();
        if ($orderPurchasedEntity && ($orderPurchasedEntity->id() == $purchasedEntity->id())) {
          $alreadyOrdered += $orderItem->getQuantity();
        }
      }
    }

    return $alreadyOrdered;
  }

}
