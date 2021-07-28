<?php

namespace Drupal\cecc_api\EventSubscriber;

use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_order\Event\OrderItemEvent;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\cecc_stock\Service\StockValidation;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Performs stock calculations on order and order item events.
 */
class Order implements EventSubscriberInterface {

  use StringTranslationTrait;
  use MessengerTrait;

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
   * @var \Drupal\po_stock\Service\StockValidation
   */
  protected $stockValidation;

  /**
   * The Queue service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Contstructs a new order event subscriber.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type Manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Logger Channel Factory.
   * @param \Drupal\cecc_stock\Service\StockValidation $stockValidation
   *   Logger Channel Factory.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   Queue Factory service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelFactoryInterface $loggerFactory,
    StockValidation $stockValidation,
    QueueFactory $queueFactory
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $loggerFactory->get('cecc_api');
    $this->stockValidation = $stockValidation;
    $this->queueFactory = $queueFactory;
  }

  /**
   * Queues a product for a stock update.
   *
   * @param \Drupal\commerce\PurchasableEntityInterface $entity
   *   The product variation.
   */
  private function queueItemStockUpdate(PurchasableEntityInterface $entity) {
    if ($this->stockValidation->isStockBelowThreshold($entity)
      && !$entity->get('field_awaiting_stock_refresh')->value
    ) {
      $entity->set('field_awaiting_stock_refresh', TRUE);

      try {
        $entity->save();

        $item = [
          'id' => $entity->id(),
          'sku' => $entity->get('sku')->value,
          'warehouse_item_id' => $entity->get('field_warehouse_item_id')->value,
        ];

        $queue = $this->queueFactory->get('po_update_stock');
        $queue->createItem($item);

        $this->messenger()->addStatus($this->t('Stock for %label has been fallen below %stockLevel. It has been queued for a stock refresh.', [
          '%label' => $entity->getOrderItemTitle(),
          '%stockLevel' => $entity->get('field_stock_threshold')->value,
        ]));
      }
      catch (EntityStorageException $error) {
        $this->logger->error($error->getMessage());
      }
    }
  }

  /**
   * Queue order to be sent.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order to be sent.
   */
  private function queueOrderSend(OrderInterface $order) {
    $queue = $this->queueFactory->get('po_send_order');
    $queue->createItem(['id' => $order->id()]);

    $this->logger->info('Order %orderNumber has been queued to be sent.', [
      '%orderNumber' => $order->getOrderNumber(),
    ]);
  }

  /**
   * Runs when after an order is placed.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The workflow event.
   */
  public function onOrderPlace(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();

    $completed = $order->getState()->getId() == 'completed';

    if ($completed) {
      $this->queueOrderSend($order);

      foreach ($order->getItems() as $item) {
        /** @var \Drupal\commerce\PurchasableEntityInterface $entity */
        $entity = $item->getPurchasedEntity();

        if (!$entity) {
          continue;
        }

        $this->queueItemStockUpdate($entity);
      }
    }

  }

  /**
   * Order update event.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The order event
   */
  public function onOrderUpdate(OrderEvent $event) {
    $order = $event->getOrder();

    $completed = $order->getState()->getId() == 'completed';

    if ($completed) {
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

            $this->queueItemStockUpdate($entity);
          }
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

      $this->queueItemStockUpdate($entity);
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

    $items = $order->getItems();

    foreach ($items as $item) {
      $entity = $item->getPurchasedEntity();

      if (!$entity) {
        continue;
      }

      $this->queueItemStockUpdate($entity);
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
    $order = $item->getOrder();

    if ($order && !in_array($order->getState()->value, ['draft', 'canceled'])) {
      $original = $this->getOriginalEntity($item);
      $diff = $original->getQuantity() - $item->getQuantity();
      if ($diff) {
        $entity = $item->getPurchasedEntity();

        if (!$entity) {
          return;
        }

        $this->queueItemStockUpdate($entity);
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

      $this->queueItemStockUpdate($entity);
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
