<?php

namespace Drupal\cecc_publication\Routing\Access;

use Drupal\commerce_product\Entity\Product;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\FlagServiceInterface;
use Symfony\Component\Routing\Route;

/**
 * Publication Route Subscriber class.
 */
class PublicationAccess implements AccessInterface {

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * The Publication route subscriber contructor.
   *
   * @param \Drupal\Core\Routing\CurrentRouteMatch $routeMatch
   *   The flag service.
   */
  public function __construct(CurrentRouteMatch $routeMatch) {
    $this->routeMatch = $routeMatch;
  }

  /**
   * {@inheritDoc}
   */
  public function access(Route $route, AccountInterface $account) {
    $product = $this->getRouteEntity($route);

    if (!$product) {
      return AccessResult::allowed();
    }

    $productVariation = $product->getDefaultVariation();
    $notAvailable = 0;

    if ($productVariation) {
      $notAvailable = $productVariation->get('field_not_available')->isEmpty()
        ? 0 : (int) $productVariation->get('field_not_available')->value;
    }

    $hasPermission = $notAvailable === 0 ?
    $account->hasPermission('view commerce_product') : $account->hasPermission('view unavailable publications');

    return $hasPermission ? AccessResult::allowed() : AccessResult::forbidden();
  }

  /**
   * Get's the route entity.
   *
   * @param Symfony\Component\Routing\Route $route
   *   The current Route.
   */
  private function getRouteEntity(Route $route) {
    $parameters = $route->getOption('parameters');

    foreach ($parameters as $name => $options) {
      if (isset($options['type'])  && strpos($options['type'], 'entity:') === 0) {
        $entity = $this->routeMatch->getParameter($name);
        if ($entity instanceof Product && $entity->hasLinkTemplate('canonical')) {
          return $entity;
        }
      }
    }

    return NULL;
  }

}
