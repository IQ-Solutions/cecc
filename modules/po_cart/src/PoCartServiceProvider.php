<?php

namespace Drupal\po_cart;

use Drupal\po_cart\EventSubscriber\PoCartEventSubscriber;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

class PoCartServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritDoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('commerce_cart.cart_subscriber')) {
      $definition = $container->getDefinition('commerce_cart.cart_subscriber');
      $definition->setClass(PoCartEventSubscriber::class)
        ->addArgument(new Reference('request_stack'));
    }
  }

}
