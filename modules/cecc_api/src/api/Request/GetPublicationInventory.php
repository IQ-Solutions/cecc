<?php

namespace Drupal\cecc_api\api\Request;

use Drupal\cecc_api\api\Commands\InventoryServicesContents;
use Drupal\http_client_manager\Request\HttpRequestBase;
use Drupal\cecc_api\api\Parameters\InventoryServicesContents as Param;

/**
 * Get inventory for one product.
 *
 * @package Drupal\cecc_api\api\Request
 */
class GetPublicationInventory extends HttpRequestBase {

  /**
   * The agency the product belongs to.
   *
   * @var string
   */
  protected $agency = NULL;

  /**
   * The API auth string.
   *
   * @var string
   */
  protected $code = NULL;

  /**
   * The warehouse item id.
   *
   * @var string
   */
  protected $warehouseItemId = NULL;

  /**
   * Get agency.
   *
   * @return string
   *   The agency id.
   */
  public function getAgency() {
    return $this->agency;
  }

  /**
   * Set the agency.
   *
   * @param string $agency
   *   Sets the agency.
   *
   * @return $this
   */
  public function setAgency($agency) {
    $this->agency = $agency;

    return $this;
  }

  /**
   * Get code.
   *
   * @return string
   *   The auth code used.
   */
  public function getCode() {
    return $this->code;
  }

  /**
   * Set the code.
   *
   * @param string $code
   *   Sets the code.
   *
   * @return $this
   */
  public function setCode($code) {
    $this->code = $code;

    return $this;
  }

  /**
   * Get code.
   *
   * @return string
   *   The auth code used.
   */
  public function getWarehouseItemId() {
    return $this->warehouseItemId;
  }

  /**
   * Set the code.
   *
   * @param string $code
   *   Sets the code.
   *
   * @return $this
   */
  public function setWarehouseItemId($warehouseItemId) {
    $this->warehouseItemId = $warehouseItemId;

    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getCommand() {
    return InventoryServicesContents::GET_PUBLICATION_INVENTORY;
  }

  /**
   * {@inheritDoc}
   */
  public function getArgs() {
    return [
      Param::AGENCY => $this->getAgency(),
      Param::CODE => $this->getCode(),
      Param::WAREHOUSE_ITEM_ID => $this->getWarehouseItemId(),
    ];
  }

}
