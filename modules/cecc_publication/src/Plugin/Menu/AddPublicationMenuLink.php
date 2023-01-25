<?php

namespace Drupal\cecc_publication\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;

/**
 * Add Product Menu Link
 */
class AddPublicationMenuLink extends MenuLinkDefault {
  public function getRouteParameters() {
    $config = \Drupal::configFactory()->get('cecc_publication.settings');

    return [
      'commerce_product_type' => $config->get('commerce_product_type'),
    ];
  }
}