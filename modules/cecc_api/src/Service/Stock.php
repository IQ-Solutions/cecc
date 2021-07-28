<?php

namespace Drupal\cecc_api\Service;

use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\http_client_manager\HttpClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The Stock API service class.
 */
class Stock implements ContainerInjectionInterface {
  use StringTranslationTrait;
  use MessengerTrait;

  /**
   * The module name.
   *
   * @var string
   */
  private $moduleName = 'cecc_api';

  /**
   * Guzzle\Client instance.
   *
   * @var \Drupal\http_client_manager\HttpClientInterface
   */
  protected $httpClient;

  /**
   * Date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * Date time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

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
   * The Queue service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Service config and DI.
   *
   * @param \Drupal\Core\Datetime\DateFormatter $dateFormatter
   *   Date formatter service.
   * @param \Drupal\Component\Datetime\TimeInterface $timeInterface
   *   Time service.
   * @param \Drupal\http_client_manager\HttpClientInterface $http_client
   *   Http client manager client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Drupal logger service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager service.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   Queue Factory service.
   * @param \Drupal\Core\Database\Connection $connection
   *   Queue Factory service.
   */
  public function __construct(
    DateFormatter $dateFormatter,
    TimeInterface $timeInterface,
    HttpClientInterface $http_client,
    LoggerChannelFactoryInterface $loggerFactory,
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactory $configFactory,
    QueueFactory $queueFactory,
    Connection $connection
  ) {
    $this->dateFormatter = $dateFormatter;
    $this->time = $timeInterface;
    $this->httpClient = $http_client;
    $this->logger = $loggerFactory->get($this->moduleName);
    $this->entityTypeManager = $entity_type_manager;
    $this->config = $configFactory->get('cecc_api.settings');
    $this->queueFactory = $queueFactory;
    $this->connection = $connection;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('datetime.time'),
      $container->get('cecc_api.http_client.contents'),
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('queue'),
      $container->get('database')
    );
  }

  /**
   * Gets all inventory.
   */
  public function refreshAllInventory() {
    $query = $this->connection->select('commerce_product_variation_field_data', 'cpv')
      ->fields('cpv.id')
      ->where('cpv.cecc_check_stock_threshold >= cpv.field_cecc_stock');

    $count = $query->countQuery()->execute()->fetchField();

    if ($count < 1) {
      $this->logger->info('There are no products that need a stock refresh.');
      return FALSE;
    }

    $ids = $query->execute()->fetchAll();

    $queue = $this->queueFactory->get('cecc_update_stock');

    foreach ($ids as $id) {
      $item = [
        'id' => $id->id,
      ];

      $queue->createItem($item);
    }

    $this->logger->info('Products queued for stock update: %count', [
      '%count' => $count,
    ]);
  }

  /**
   * Refresh a single product variations stock.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $productVariation
   *   The product variation.
   */
  public function refreshInventory(ProductVariationInterface $productVariation) {
    $agency = $this->config->get('agency');
    $apiKey = $this->config->get('api_key');

    if (empty($apiKey) || empty($agency)) {
      $message = 'An API Key and service ID must be entered.';

      $this->logger->error($message);
      $this->messenger()->addError($message);
    }

    $params = [
      'agency' => $this->config->get('agency'),
      'warehouse_item_id' => $productVariation->get('field_cecc_warehouse_item_id')->value,
      'code' => $this->config->get('api_key'),
    ];

    try {
      $response = $this->httpClient
        ->call('GetSingleInventory', $params);

      if ($response['code'] != 200) {
        $message = $this->t('The service failed with the following error: %error', [
          '%error' => $response['message'],
        ]);

        $this->logger->error($message);
        $this->messenger()->addError($message);
      }

      $productVariation->set('field_cecc_stock', $response['inventory']['warehouse_stock_on_hand']);
      $productVariation->set('field_awaiting_stock_refresh', FALSE);

      try {
        $productVariation->save();
        $message = $this->t('Stock for %label has been refreshed to %level', [
          '%label' => $productVariation->getTitle(),
          '%level' => $productVariation->get('field_cecc_stock')->value,
        ]);

        $this->logger->info($message);
        $this->messenger()->addStatus($message);
      }
      catch (EntityStorageException $error) {
        $this->logger->error($error->getMessage());
      }
    }
    catch (\Exception $error) {
      $this->messenger()->addError($this->t('@label failed to update. Check the error logs for more information.', [
        '%label' => $productVariation->getTitle(),
      ]));

      $this->logger->error('@label failed to update. @error', [
        '%label' => $productVariation->getTitle(),
        '@error' => $error->getMessage(),
      ]);
    }
  }

}
