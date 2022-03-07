<?php

namespace Drupal\cecc_api\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\http_client_manager\HttpClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Inventory API service.
 */
class InventoryApi implements ContainerInjectionInterface {
  use StringTranslationTrait;

  /**
   * Guzzle\Client instance.
   *
   * @var \Drupal\http_client_manager\HttpClientInterface
   */
  protected $httpClient;

  /**
   * Drupal logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The agency abberviation/id.
   *
   * @var string
   */
  private $agency;

  /**
   * The API key.
   *
   * @var string
   */
  private $apiKey;

  /**
   * Is API active and available.
   *
   * @var bool
   */
  public $apiActive = TRUE;

  /**
   * The connection error message.
   *
   * @var string
   */
  public $connectionError;

  /**
   * Inventory API service contructor.
   *
   * @param \Drupal\http_client_manager\HttpClientInterface $http_client
   *   The HTTP client manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory object.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory object.
   */
  public function __construct(
    HttpClientInterface $http_client,
    LoggerChannelFactoryInterface $logger_factory,
    ConfigFactoryInterface $config_factory
  ) {
    $this->httpClient = $http_client;
    $this->logger = $logger_factory->get('cecc_api');
    $this->config = $config_factory->get('cecc_api.settings');
    $this->agency = $this->config->get('agency');
    $this->apiKey = $this->config->get('api_key');

    if (empty($this->apiKey) || empty($this->agency)) {
      $message = 'An API Key and service ID must be entered. The API is currently disabled.';

      $this->logger->error($message);
      $this->apiActive = FALSE;
    }
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cecc_api.http_client.contents'),
      $container->get('logger.factory'),
      $container->get('config.factory')
    );
  }

  /**
   * Connects to specified endpoint.
   *
   * @param string $endpoint
   *   The endpoint.
   * @param array $params
   *   The endpoint params.
   *
   * @return array
   *   The response array.
   */
  private function connectToService($endpoint, array $params) {
    try {
      /** @var \GuzzleHttp\Command\ResultInterface $response */
      $response = $this->httpClient
        ->call($endpoint, $params);

      if ($response['code'] != 200) {
        $message = $this->t('Error Code: %code - The service failed with the following error: %error', [
          '%error' => $response['message'],
        ]);

        $this->logger->error($message);
        $this->logger->error(print_r($response, TRUE));
      }

      if (!isset($response['Catalog'])) {
        $message = $this->t('No catalog data was found.');

        $this->logger->error($message);
        $this->logger->error(print_r($response, TRUE));
      }

      if (isset($response['Catalog']) && empty($response['Catalog'])) {
        $message = $this->t('Catalog data returned empty.');

        $this->logger->error($message);
        $this->logger->error(print_r($response, TRUE));
      }

      return $response;
    }
    catch (\Exception $error) {
      $this->connectionError = $error->getMessage();
      return [];
    }
  }

  /**
   * Get the stock for a single publication.
   *
   * @return array
   *   The response.
   */
  public function getAllInventory() {
    if (!$this->apiActive) {
      return [];
    }

    $params = [
      'agency' => $this->agency,
      'code' => $this->apiKey,
    ];

    $response = $this->connectToService('GetAllInventory', $params);

    return $response['Catalog'];
  }

  /**
   * Get the stock for a single publication.
   *
   * @param string $warehouse_item_id
   *   The warehouse item id.
   *
   * @return array
   *   The response.
   */
  public function getSingleInventory($warehouse_item_id) {
    if (!$this->apiActive) {
      return [];
    }

    $params = [
      'agency' => $this->agency,
      'warehouse_item_id' => $warehouse_item_id,
      'code' => $this->apiKey,
    ];

    $response = $this->connectToService('GetSingleInventory', $params);

    return $response['inventory'];
  }

}
