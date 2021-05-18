<?php

namespace Drupal\po_cart\EventSubscriber;

use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\commerce_cart\Event\CartOrderItemRemoveEvent;
use Drupal\commerce_cart\Event\CartOrderItemUpdateEvent;
use Drupal\commerce_cart\EventSubscriber\CartEventSubscriber;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Adds ajax override for cart messages.
 */
class PoCartEventSubscriber extends CartEventSubscriber {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * Constructs a new CartEventSubscriber object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(
    MessengerInterface $messenger,
    TranslationInterface $string_translation,
    RequestStack $request_stack) {
    parent::__construct($messenger, $string_translation);

    $this->currentRequest = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritDoc}
   */
  public function displayAddToCartMessage(CartEntityAddEvent $event) {
    $isAjax = $this->currentRequest->isXmlHttpRequest();
    $entity = $event->getEntity();

    Cache::invalidateTags(['commerce_product:' . $entity->id()]);

    if (!$isAjax) {
      parent::displayAddToCartMessage($event);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      CartEvents::CART_ENTITY_ADD => 'displayAddToCartMessage',
      CartEvents::CART_ORDER_ITEM_REMOVE => 'onOrderItemRemove',
      CartEvents::CART_ORDER_ITEM_UPDATE => 'onOrderItemUpdate',
    ];

    return $events;
  }

  public function onOrderItemRemove(CartOrderItemRemoveEvent $event) {
    $item = $event->getOrderItem();
    $entity = $item->getPurchasedEntity();

    if (!$entity) {
      return;
    }

    Cache::invalidateTags(['commerce_product:' . $entity->id()]);
  }

  public function onOrderItemUpdate(CartOrderItemUpdateEvent $event) {
    $item = $event->getOrderItem();
    $entity = $item->getPurchasedEntity();

    if (!$entity) {
      return;
    }

    Cache::invalidateTags(['commerce_product:' . $entity->id()]);
  }

}
