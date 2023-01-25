<?php

namespace Drupal\cecc_stock\EventSubscriber;

use Drupal\cecc_stock\Service\StockHelper;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_order\Event\OrderItemEvent;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\cecc_stock\Service\StockValidation;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Performs stock calculations on order and order item events.
 */
class Order implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type Manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Logger Channel Factory.
   * @param \Drupal\cecc_stock\Service\StockValidation $stockValidation
   *   Logger Channel Factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelFactoryInterface $loggerFactory,
    StockValidation $stockValidation
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $loggerFactory->get('cecc');
    $this->stockValidation = $stockValidation;
  }

  /**
   * Modify Product stock level.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $orderItem
   *   The order item.
   * @param \Drupal\commerce\PurchasableEntityInterface $productVariation
   *   The product variation in the order.
   */
  private function modifyStockLevel(
    OrderItemInterface $orderItem,
    PurchasableEntityInterface $productVariation) {
    $stock_field_name = StockHelper::getStockFieldName($productVariation);

    $quantity = -1 * $orderItem->getQuantity();
    $stock = $productVariation->get($stock_field_name)
      ->value + $quantity;
    $productVariation->set($stock_field_name, $stock);

    try {
      $productVariation->save();
    }
    catch (EntityStorageException $error) {
      $this->logger->error($error->getMessage());
    }
  }

  /**
   * Fires when an order is placed.
   *
   * @param Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The workflow event.
   */
  public function onOrderPlace(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();

    foreach ($order->getItems() as $item) {
      /** @var \Drupal\commerce\PurchasableEntityInterface $entity */
      $entity = $item->getPurchasedEntity();

      if (!$entity) {
        continue;
      }

      $this->modifyStockLevel($item, $entity);
    }

  }

  public function onOrderUpdate(OrderEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getOrder();
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $originalOrder = $this->getOriginalEntity($order);

    foreach ($order->getItems() as $item) {
      if (!$originalOrder->hasItem($item)) {
        if ($order && !in_array($order->getState()->value, [
          'draft',
          'canceled',
        ])) {
          /** @var \Drupal\commerce\PurchasableEntityInterface $entity */
          $entity = $item->getPurchasedEntity();

          if (!$entity) {
            continue;
          }

          $this->modifyStockLevel($item, $entity);
        }

      }
    }

  }

  /**
   * Performs a stock transaction for an order Cancel event.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The order workflow event.
   */
  public function onOrderCancel(WorkflowTransitionEvent $event) {
    $order = $event->getEntity();
    $original_order = $this->getOriginalEntity($order);

    if ($original_order && $original_order->getState()->value === 'draft') {
      return;
    }
    foreach ($order->getItems() as $item) {
      $entity = $item->getPurchasedEntity();

      if (!$entity) {
        continue;
      }

      $quantity = $item->getQuantity();
      $stock_field_name = StockHelper::getStockFieldName($entity);
      $stock = $entity->get($stock_field_name)->value + $quantity;
      $entity->set($stock_field_name, $stock);

      try {
        $entity->save();
      }
      catch (EntityStorageException $error) {
        $this->logger->error($error->getMessage());
      }
    }
  }

  /**
   * Performs a stock transaction on an order delete event.
   *
   * This happens on PREDELETE since the items are not available after DELETE.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The order event.
   */
  public function onOrderDelete(OrderEvent $event) {
    $order = $event->getOrder();
    if (in_array($order->getState()->value, ['draft', 'canceled'])) {
      return;
    }
  }

  /**
   * Performs a stock transaction on an order item update.
   *
   * @param \Drupal\commerce_order\Event\OrderItemEvent $event
   *   The order item event.
   */
  public function onOrderItemUpdate(OrderItemEvent $event) {
    $item = $event->getOrderItem();
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $item->getOrder();

    if ($order && !in_array($order->getState()->value, ['draft', 'canceled'])) {
      /** @var \Drupal\commerce_order\Entity\OrderItemterface $original */
      $original = $this->getOriginalEntity($item);
      $diff = $original->getQuantity() - $item->getQuantity();
      if ($diff) {
        $entity = $item->getPurchasedEntity();

        if (!$entity) {
          return;
        }

        $stock_field_name = StockHelper::getStockFieldName($entity);

        $stock = $entity->get($stock_field_name)->value + $diff;
        $entity->set($stock_field_name, $stock);

        try {
          $entity->save();
        }
        catch (EntityStorageException $error) {
          $this->logger->error($error->getMessage());
        }
      }
    }
  }

  /**
   * Performs a stock transaction when an order item is deleted.
   *
   * @param \Drupal\commerce_order\Event\OrderItemEvent $event
   *   The order item event.
   */
  public function onOrderItemDelete(OrderItemEvent $event) {
    $item = $event->getOrderItem();
    $order = $item->getOrder();

    if ($order && !in_array($order->getState()->value, ['draft', 'canceled'])) {
      $entity = $item->getPurchasedEntity();

      if (!$entity) {
        return;
      }

      $stock_field_name = StockHelper::getStockFieldName($entity);

      $stock = $entity->get($stock_field_name)->value + $item->getQuantity();
      $entity->set($stock_field_name, $stock);

      try {
        $entity->save();
      }
      catch (EntityStorageException $error) {
        $this->logger->error($error->getMessage());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      // State change events fired on workflow transitions from state_machine.
      'commerce_order.place.post_transition' => ['onOrderPlace', -100],
      'commerce_order.cancel.post_transition' => ['onOrderCancel', -100],
      // Order storage events dispatched during entity operations in
      // CommerceContentEntityStorage.
      // ORDER_UPDATE handles new order items since ORDER_ITEM_INSERT doesn't.
      OrderEvents::ORDER_UPDATE => ['onOrderUpdate', -100],
      OrderEvents::ORDER_PREDELETE => ['onOrderDelete', -100],
      OrderEvents::ORDER_ITEM_UPDATE => ['onOrderItemUpdate', -100],
      OrderEvents::ORDER_ITEM_DELETE => ['onOrderItemDelete', -100],
    ];
    return $events;
  }

  /**
   * Returns the entity from an updated entity object. In certain
   * cases the $entity->original property is empty for updated entities. In such
   * a situation we try to load the unchanged entity from storage.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The changed/updated entity object.
   *
   * @return null|\Drupal\Core\Entity\EntityInterface
   *   The original unchanged entity object or NULL.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getOriginalEntity(EntityInterface $entity) {
    // $entity->original only exists during save. See
    // \Drupal\Core\Entity\EntityStorageBase::save().
    // If we don't have $entity->original we try to load it.
    $original_entity = NULL;
    $original_entity = $entity->original;

    // @ToDo Consider how this may change due to: ToDo https://www.drupal.org/project/drupal/issues/2839195
    if (!$original_entity) {
      $id = $entity->getOriginalId() !== NULL ? $entity->getOriginalId() : $entity->id();
      $original_entity = $this->entityTypeManager
        ->getStorage($entity->getEntityTypeId())
        ->loadUnchanged($id);
    }

    return $original_entity;
  }

}
