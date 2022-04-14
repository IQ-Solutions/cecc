<?php

namespace Drupal\cecc_api\Plugin\HttpServiceApiWrapper;

use Drupal\cecc_api\api\Request\GetPublicationInventories;
use Drupal\cecc_api\api\Request\GetPublicationInventory;

/**
 * The Inventory services contents interface.
 *
 * @package Drupal\cecc_api\api\Plugin\HttpServiceApiWrapper
 */
interface CeccApiInventoryServicesContentsInterface {

  const SERVICE_API = 'cecc_api.contents';

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

  /**
   * Get Publication Inventory.
   *
   * @param \Drupal\cecc_api\api\Request\GetPublicationInventory $request
   *   The HTTP request object.
   *
   * @return array
   *   The service response.
   */
  public function getPublicationInventory(GetPublicationInventory $request);

}
