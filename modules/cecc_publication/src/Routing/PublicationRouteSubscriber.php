<?php

namespace Drupal\cecc_publication\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class PublicationRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Define custom access for '/user/logout'.
    if ($route = $collection->get('entity.commerce_product.canonical')) {
      $route->setRequirement('_cecc_access', 'cecc_publication.publication_access::access');
    }
  }

}
