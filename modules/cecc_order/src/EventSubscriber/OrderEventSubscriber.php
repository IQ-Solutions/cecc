<?php

namespace Drupal\cecc_order\EventSubscriber;

use Drupal\cecc_order\Service\ReconcileCart;
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
   * @var \Drupal\cecc_order\Service\ReconcileCart
   */
  protected $reconcileCart;

  /**
   * CartEventSubscriber constructor.
   *
   * @param \Drupal\cecc_order\Service\ReconcileCart $reconcile_cart
   *   The cart merge service.
   */
  public function __construct(ReconcileCart $reconcile_cart) {
    $this->reconcileCart = $reconcile_cart;
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

    if (!$order->get('cart')->isEmpty()) {
      $this->reconcileCart->assignCart($order, $event->getCustomer());
    }
  }

}
