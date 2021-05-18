<?php

namespace Drupal\publication_ordering\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Admin Display class.
 */
class AdminDisplay extends ControllerBase {

  /**
   * The general tab for the Catalog config.
   */
  public function generalTab() {
    return [
      'view' => [
        '#theme' => 'catalog_admin_general',
      ],
    ];
  }

}
