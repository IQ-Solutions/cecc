<?php

namespace Drupal\cecc_api\Plugin\HttpServiceApiWrapper;

use Drupal\cecc_api\api\Request\GetPublicationInventories;

/**
 * The Inventory services contents interface.
 *
 * @package Drupal\cecc_api\api\Plugin\HttpServiceApiWrapper
 */
interface CeccApiInventoryServicesContentsInterface {

  const SERVICE_API = 'inventory_services.contents';

  /**
   * Get Publication Inventories.
   *
   * @param \Drupal\cecc_api\api\Request\GetPublicationInventories $request
   *   The HTTP request object.
   *
   * @return array
   *   The service response.
   */
  public function getPublicationInventories(GetPublicationInventories $request);

}
