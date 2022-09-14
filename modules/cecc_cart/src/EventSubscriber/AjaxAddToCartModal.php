<?php

namespace Drupal\cecc_cart\EventSubscriber;

use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber to display a popup when items are add to cart via AJAX.
 */
class AjaxAddToCartModal implements EventSubscriberInterface {

  /**
   * The entity that was added to the cart.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariationInterface
   */
  protected $purchasedEntity;

  /**
   * EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * Constructs a new AjaxAddToCartPopupSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Used to display the rendered product_variation entity.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    CartProviderInterface $cart_provider) {
    $this->entityTypeManager = $entity_type_manager;
    $this->cartProvider = $cart_provider;
  }

  /**
   * Adds the modal confirmation message on page.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onResponse(ResponseEvent $event) {
    $response = $event->getResponse();

    if (!$this->purchasedEntity) {
      return;
    }

    if (!$response instanceof AjaxResponse) {
      return;
    }

    if (TRUE) {
      $build = $this->displayCartModalTheme();
    }
    else {
      $build = $this->displaySingleProductModalTheme();
    }

    $selector = '#catalog' . $this->purchasedEntity->id();

    $title = '';
    $options = [
      'width' => 'auto',
      'height' => 'auto',
      'draggable' => FALSE,
      'closeText' => 'Close',
      'autoResize' => 'false',
    ];

    //$response->addCommand(new CloseModalDialogCommand());
    //$response->addCommand(new OpenModalDialogCommand($title, $build, $options));
    //$response->addCommand(new PopoverCommand($selector, $build));
    $event->setResponse($response);
  }

  /**
   * Displays the added product in the modal.
   */
  private function displayCartModalTheme() {
    /** @var \Drupal\commerce_order\Entity\OrderInterface[] $carts */
    $carts = $this->cartProvider->getCarts();
    $carts = array_filter($carts, function ($cart) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $cart */
      // There is a chance the cart may have converted from a draft order, but
      // is still in session. Such as just completing check out. So we verify
      // that the cart is still a cart.
      return $cart->hasItems() && $cart->cart->value;
    });
    $viewBuilder = $this->entityTypeManager->getViewBuilder('commerce_order_item');

    $orderItemArray = [];

    // @todo make this configurable later.
    if (!empty($carts)) {
      foreach ($carts as $cart) {
        foreach ($cart->getItems() as $order_item) {
          $orderItemArray[] = $viewBuilder->view($order_item, 'ajax_cart');
        }
      }
    }

    $build = [
      '#theme' => 'cecc_cart_show_cart_modal',
      '#order_items' => $orderItemArray,
      '#purchased_entity' => $this->purchasedEntity->id(),
      '#cart_url' => Url::fromRoute('commerce_cart.page')->toString(),
    ];

    return $build;
  }

  /**
   * Displays the added product in the modal.
   */
  private function displaySingleProductModalTheme() {
    $viewBuilder = $this->entityTypeManager->getViewBuilder('commerce_product_variation');
    $productVariation = $viewBuilder->view($this->purchasedEntity, 'cart');

    $build = [
      '#theme' => 'cecc_cart_add_cart_modal',
      '#product_variation' => $productVariation,
      '#product_variation_entity' => $this->purchasedEntity,
      '#cart_url' => Url::fromRoute('commerce_cart.page')->toString(),
    ];

    return $build;
  }

  /**
   * Initializes the purchased entity.
   *
   * @param \Drupal\commerce_cart\Event\CartEntityAddEvent $event
   *   The add to cart event.
   */
  public function onAddToCart(CartEntityAddEvent $event) {
    $this->purchasedEntity = $event->getEntity();
  }

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::RESPONSE => 'onResponse',
      CartEvents::CART_ENTITY_ADD => 'onAddToCart',
    ];
  }

}
