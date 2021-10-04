<?php

namespace Drupal\cecc_order\Service;

use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\user\UserInterface;

/**
 * Reconcile commerce carts on user login.
 */
class ReconcileCart {

  /**
   * The cart provider service.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * The cart manager service.
   *
   * @var \Drupal\commerce_cart\CartManagerInterface
   */
  protected $cartManager;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * CartMerge constructor.
   *
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   *   The cart provider.
   * @param \Drupal\commerce_cart\CartManagerInterface $cart_manager
   *   The cart manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(
    CartProviderInterface $cart_provider,
    CartManagerInterface $cart_manager,
    RouteMatchInterface $route_match,
    EntityTypeManagerInterface $entity_type_manager) {
    $this->cartProvider = $cart_provider;
    $this->cartManager = $cart_manager;
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Assign a cart to a user, moving items to the user's main cart.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $cart
   *   The cart to assign.
   * @param \Drupal\user\UserInterface $user
   *   The user.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function assignCart(OrderInterface $cart, UserInterface $user) {
    $userCarts = $this->cartProvider->getCarts($user);

    if ($userCarts) {
      foreach ($userCarts as $userCart) {
        if ($cart->bundle() != $userCart->bundle()) {
          continue;
        }

        $this->removeCart($userCart);
      }
    }
  }

  /**
   * Removes cart.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $cart
   *   The cart to delete.
   */
  private function removeCart(OrderInterface $cart) {
    $cartItems = $cart->getItems();

    foreach ($cartItems as $cartItem) {
      $cart->removeItem($cartItem);
      $cartItem->delete();
    }

    $cart->delete();
  }

  /**
   * Merges cart into the main cart and optionally deletes the other cart.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $anonymous_cart
   *   The anonymous cart.
   * @param \Drupal\commerce_order\Entity\OrderInterface $loggedin_cart
   *   The logged in cart cart.
   * @param bool $delete
   *   TRUE to delete the other cart when finished, FALSE to save it as empty.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function mergeCarts(OrderInterface $anonymous_cart, OrderInterface $loggedin_cart, $delete = FALSE) {
    if ($anonymous_cart->id() === $loggedin_cart->id()) {
      return;
    }

    foreach ($loggedin_cart->getItems() as $item) {
      $loggedin_cart->removeItem($item);
      $item->get('order_id')->entity = $anonymous_cart;
      $combine = $this->shouldCombineItem($item);
      $this->cartManager->addOrderItem($anonymous_cart, $item, $combine);
    }
    $loggedin_cart->delete();

    $anonymous_cart->save();
  }

  /**
   * Determine if a line item should be combined with like items.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $item
   *   The order item.
   *
   * @return bool
   *   TRUE if items should be combined, FALSE otherwise.
   */
  private function shouldCombineItem(OrderItemInterface $item) {
    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $purchased_entity */
    $purchased_entity = $item->getPurchasedEntity();

    // Do not combine products which are no longer available in system.
    if (!($purchased_entity instanceof ProductVariationInterface)) {
      return FALSE;
    }

    $product = $purchased_entity->getProduct();
    /** @var \Drupal\Core\Entity\Entity\EntityViewDisplay $entityDisplay */
    $entityDisplay = $this->entityTypeManager->getStorage('entity_view_display')
      ->load($product->getEntityTypeId() . '.' . $product->bundle() . '.default');
    $combine = TRUE;

    if ($component = $entityDisplay->getComponent('variations')) {
      $combine = !empty($component['settings']['combine']);
    }

    return $combine;
  }

  /**
   * Returns the cart requested for checkout.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface|null
   *   The cart.
   */
  protected function getCartRequestedForCheckout() {
    if ($this->routeMatch->getRouteName() === 'commerce_checkout.form') {
      $requested_order = $this->routeMatch->getParameter('commerce_order');

      if ($requested_order) {
        return $requested_order;
      }
    }

    return NULL;
  }

  /**
   * Returns TRUE if given cart is requested for checkout.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $cart
   *   The cart.
   *
   * @return bool
   *   True if requested. False if not.
   */
  protected function isCartRequestedForCheckout(OrderInterface $cart) {
    $requested_cart = $this->getCartRequestedForCheckout();
    return $requested_cart && $requested_cart->id() === $cart->id();
  }

}
