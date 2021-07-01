<?php

namespace Drupal\cecc;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class to modify Drupal DI.
 */
class CeccServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritDoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('form_error_handler')) {
      $definition = $container->getDefinition('form_error_handler');
      $definition->setClass(CeccFormErrorHandler::class)
        ->setArguments([
          new Reference('string_translation'),
          new Reference('renderer'),
          new Reference('messenger'),
        ]);
    }
  }

}
