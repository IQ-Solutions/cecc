<?php

namespace Drupal\cecc_cart;

use Drupal\commerce_cart\CartManager;
use Drupal\commerce_cart\Event\CartOrderItemAddEvent;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\OrderItemMatcherInterface;
use Drupal\commerce_price\Calculator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Default implementation of the cart manager.
 *
 * Fires its own events, different from the order entity events by being a
 * result of user interaction (add to cart form, cart view, etc).
 */
class CeccCartManager extends CartManager {

  /**
   * Drupal config service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * Constructs a new CartManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_cart\OrderItemMatcherInterface $order_item_matcher
   *   The order item matcher.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    OrderItemMatcherInterface $order_item_matcher,
    EventDispatcherInterface $event_dispatcher,
    ConfigFactoryInterface $config_factory) {
    $this->orderItemStorage = $entity_type_manager->getStorage('commerce_order_item');
    $this->orderItemMatcher = $order_item_matcher;
    $this->eventDispatcher = $event_dispatcher;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function addOrderItem(OrderInterface $cart, OrderItemInterface $order_item, $combine = TRUE, $save_cart = TRUE) {

    $commerceConfig = $this->configFactory->get('cecc.settings');
    $addToCartType = $commerceConfig->get('quantity_update_type');

    if ($addToCartType == 'normal') {
      return parent::addOrderItem($cart, $order_item, $combine, $save_cart);
    }

    $purchased_entity = $order_item->getPurchasedEntity();
    $quantity = $order_item->getQuantity();
    $matching_order_item = NULL;

    if ($combine) {
      $matching_order_item = $this->orderItemMatcher->match($order_item, $cart->getItems());
    }

    if ($matching_order_item) {
      if ($addToCartType) {
        $new_quantity = $quantity;
      }
      else {
        $new_quantity = Calculator::add($matching_order_item->getQuantity(), $quantity);
      }

      $matching_order_item->setQuantity($new_quantity);
      $matching_order_item->save();
      $saved_order_item = $matching_order_item;
    }
    else {
      $order_item->set('order_id', $cart->id());
      $order_item->save();
      $cart->addItem($order_item);
      $saved_order_item = $order_item;
    }

    if ($purchased_entity) {
      $event = new CartEntityAddEvent($cart, $purchased_entity, $quantity, $saved_order_item);
      $this->eventDispatcher->dispatch(CartEvents::CART_ENTITY_ADD, $event);
    }

    $event = new CartOrderItemAddEvent($cart, $quantity, $saved_order_item);
    $this->eventDispatcher->dispatch(CartEvents::CART_ORDER_ITEM_ADD, $event);

    $this->resetCheckoutStep($cart);
    if ($save_cart) {
      $cart->save();
    }

    return $saved_order_item;
  }

}
