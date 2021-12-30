<?php

namespace Drupal\cecc_free_shipping\EventSubscriber;

use Drupal\commerce_order\Event\OrderProfilesEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Add free shipping profiles when on profile event is fired.
 */
class ProfileSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'commerce_order.profiles' => ['onProfiles'],
    ];
  }

  /**
   * Adds the free shipping profile to the order profiles.
   *
   * @param \Drupal\commerce_order\Event\OrderProfilesEvent $event
   *   The order profiles event.
   */
  public function onProfiles(OrderProfilesEvent $event) {
    $order = $event->getOrder();
    if (!$order->get('cecc_shipping_profile')->isEmpty()) {
      $event->addProfile('cecc_shipping', $order->get('cecc_shipping_profile')->entity);
    }
  }

}
