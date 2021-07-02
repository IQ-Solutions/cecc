<?php

namespace Drupal\cecc_api\Plugin\QueueWorker;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\http_client_manager\HttpClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes items for updating stocking value.
 */
class UpdateStockQueueWorkerBase extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * Drupal logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Data storage query.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Guzzle\Client instance.
   *
   * @var \Drupal\http_client_manager\HttpClientInterface
   */
  protected $httpClient;

  /**
   * Queueworker Construct.
   *
   * @param \Drupal\http_client_manager\HttpClientInterface $http_client
   *   Http client manager client.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Drupal logger service.
   */
  public function __construct(
    HttpClientInterface $http_client,
    ConfigFactory $configFactory,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $loggerFactory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $loggerFactory->get('cecc_api');
    $this->config = $configFactory->get('cecc_api.settings');
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition) {
    return new static(
      $container->get('cecc_api.http_client.contents'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($item) {
    $agency = $this->config->get('agency');
    $apiKey = $this->config->get('api_key');

    if (empty($apiKey) || empty($agency)) {
      $message = 'An API Key and service ID must be entered.';

      $this->logger->error($message);

      throw new SuspendQueueException($message);
    }

    /**
     * @var \Drupal\commerce_product\Entity\ProductVariationInterface $productVariation
     */
    $productVariation = $this->entityTypeManager->getStorage('commerce_product_variation')
      ->load($item['id']);

    if (is_null($productVariation)) {
      $this->logger->warning('Product does not exist: @id', ['@id', $item['id']]);
      return FALSE;
    }

    $params = [
      'agency' => $this->config->get('agency'),
      'warehouse_item_id' => $productVariation->get('field_warehouse_item_id')->value,
      'code' => $this->config->get('api_key'),
    ];

    try {
      $response = $this->httpClient
        ->call('GetSingleInventory', $params);

      if ($response['code'] != 200) {
        $message = $this->t('The service failed with the follow error: %error', [
          '%error' => $response['message'],
        ]);

        $this->logger->error($message);
        throw new SuspendQueueException($message);
      }

      $productVariation->set('field_po_stock', $response['inventory']['warehouse_stock_on_hand']);
      $productVariation->set('field_awaiting_stock_refresh', FALSE);

      try {
        $productVariation->save();
        $this->logger->info('Stock for %label has been refreshed to %level', [
          '%label' => $productVariation->getTitle(),
          '%level' => $productVariation->get('field_po_stock')->value,
        ]);
      }
      catch (EntityStorageException $error) {
        $this->logger->error($error->getMessage());
        throw new RequeueException($error->getMessage());
      }
    }
    catch (\Exception $error) {
      $this->logger->error('@error', [
        '@error' => $error->getMessage(),
      ]);
      throw new SuspendQueueException($error->getMessage());
    }
  }

}
