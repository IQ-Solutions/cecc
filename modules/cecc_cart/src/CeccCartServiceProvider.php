<?php

namespace Drupal\cecc_cart;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\cecc_cart\CeccCartManager;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class to modify Drupal DI.
 */
class CeccCartServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritDoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('commerce_cart.cart_manager')) {
      $definition = $container->getDefinition('commerce_cart.cart_manager');
      $definition->setClass(CeccCartManager::class)
        ->addArgument(new Reference('config.factory'));
    }
  }

}
