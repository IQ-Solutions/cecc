<?php

namespace Drupal\cecc_api\EventSubscriber;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_order\Event\OrderItemEvent;
use Drupal\Core\Entity\EntityInterface;
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
   * @var \Drupal\cecc_stock\Service\StockValidation
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
   * Queue order to be sent.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order to be sent.
   */
  private function queueOrderSend(OrderInterface $order) {
    $queue = $this->queueFactory->get('cecc_send_order');
    $queue->createItem(['id' => $order->id()]);

    $this->logger->info('Order %orderNumber has been queued to be sent.', [
      '%orderNumber' => $order->getOrderNumber(),
    ]);
  }

  /**
   * Gets the send order state for a workflow.
   *
   * @param Drupal\commerce_order\entity\OrderInterface $order
   *   The order.
   *
   * @return bool
   *   True if is a valid send order state.
   */
  private function shouldSendOrder(OrderInterface $order) {
    $orderState = $order->getState();
    $workflow = $orderState->getWorkflow();
    $sendOrderState = $workflow->getId() == 'cecc_order_default' ?
    'fulfillment' : 'completed';

    return $orderState->getId() == $sendOrderState;
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

    if ($this->shouldSendOrder($order)) {
      $this->queueOrderSend($order);

      foreach ($order->getItems() as $item) {
        /** @var \Drupal\commerce\PurchasableEntityInterface $entity */
        $entity = $item->getPurchasedEntity();

        if (!$entity) {
          continue;
        }
      }
    }

  }

  /**
   * Order update event.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The order event.
   */
  public function onOrderUpdate(OrderEvent $event) {
    $order = $event->getOrder();

    if ($this->shouldSendOrder($order)) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $originalOrder */
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
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    /** @var \Drupal\commerce_order\Entity\OrderInterface $original_order */
    $original_order = $this->getOriginalEntity($order);

    if ($original_order && $original_order->getState()->value === 'draft') {
      return;
    }
    foreach ($order->getItems() as $item) {
      $entity = $item->getPurchasedEntity();

      if (!$entity) {
        continue;
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
    $order = $item->getOrder();

    if ($order && !in_array($order->getState()->value, ['draft', 'canceled'])) {
      /** @var \Drupal\commerce_order\Entity\OrderItemInterface $original */
      $original = $this->getOriginalEntity($item);
      $diff = $original->getQuantity() - $item->getQuantity();
      if ($diff) {
        $entity = $item->getPurchasedEntity();

        if (!$entity) {
          return;
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
   * Returns the entity from an updated entity object.
   *
   * The $entity->original property can be empty for updated entities.
   *
   * In such a situation we try to load the unchanged entity from storage.
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
