<?php

namespace Drupal\cecc_api\Plugin\HttpServiceApiWrapper;

use Drupal\cecc_api\api\Request\GetPublicationInventories;
use Drupal\cecc_api\api\Request\GetPublicationInventory;
use Drupal\cecc_api\Mail\CeccApiMailTrait;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\http_client_manager\Plugin\HttpServiceApiWrapper\HttpServiceApiWrapperBase;
use GuzzleHttp\Command\Exception\CommandException;

/**
 * The Inventory services contents wrapper.
 */
class CeccApiInventoryServicesContents extends HttpServiceApiWrapperBase implements CeccApiInventoryServicesContentsInterface {

  use LoggerChannelTrait;
  use CeccApiMailTrait;

  /**
   * {@inheritDoc}
   */
  public function getHttpClient() {
    return $this->httpClientFactory->get(self::SERVICE_API);
  }

  /**
   * {@inheritDoc}
   */
  public function getPublicationInventories(GetPublicationInventories $request) {
    return $this->callByRequest($request);
  }

  /**
   * {@inheritDoc}
   */
  public function getPublicationInventory(GetPublicationInventory $request) {
    return $this->callByRequest($request);
  }

  /**
   * {@inheritdoc}
   */
  protected function logError(CommandException $e) {
    // Better not showing the error with a message on the screen.
    $this->getLogger(self::SERVICE_API)->debug($e->getMessage());
    $params = [
      'message' => $e->getMessage(),
    ];

    $this->sendMail($params);
  }

}
