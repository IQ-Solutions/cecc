<?php

namespace Drupal\cecc_order\EventSubscriber;

use Drupal\cecc_order\Service\CartMerge;
use Drupal\commerce_order\Event\OrderAssignEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class OrderEventSubscriber.
 *
 * @package Drupal\commerce_customizations\EventSubscriber
 */
class OrderEventSubscriber implements EventSubscriberInterface {

  /**
   * The cart merge service.
   *
   * @var \Drupal\cecc_order\Service\CartMerge
   */
  protected $cartMerge;

  /**
   * CartEventSubscriber constructor.
   *
   * @param \Drupal\cecc_order\Service\CartMerge $cart_merge
   *   The cart merge service.
   */
  public function __construct(CartMerge $cart_merge) {
    $this->cartMerge = $cart_merge;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      OrderEvents::ORDER_ASSIGN => 'onOrderAssign',
    ];
    return $events;
  }

  /**
   * React when an order is being assigned to a user.
   *
   * @param \Drupal\commerce_order\Event\OrderAssignEvent $event
   *   The event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onOrderAssign(OrderAssignEvent $event) {
    $order = $event->getOrder();

    if (!$order->get('cart')->isEmpty() && $order->get('cart')->value) {
      $this->cartMerge->assignCart($order, $event->getCustomer());
    }
  }

}
