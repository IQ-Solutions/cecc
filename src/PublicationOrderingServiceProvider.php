<?php

namespace Drupal\publication_ordering;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\publication_ordering\PoCartManager;
use Symfony\Component\DependencyInjection\Reference;

class PublicationOrderingServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritDoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('commerce_cart.cart_manager')) {
      $definition = $container->getDefinition('commerce_cart.cart_manager');
      $definition->setClass(PoCartManager::class)
        ->addArgument(new Reference('config.factory'));
    }
    if ($container->hasDefinition('form_error_handler')) {
      $definition = $container->getDefinition('form_error_handler');
      $definition->setClass(PoFormErrorHandler::class)
        ->setArguments([
          new Reference('string_translation'),
          new Reference('renderer'),
          new Reference('messenger'),
        ]);
    }
  }

}
