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
   * @var \Drupal\cecc_api\Service\InventoryApi
   */
  protected $inventoryApi;

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
   * @param \Drupal\cecc_api\Service\InventoryApi $inventory_api
   *   Inventory API service.
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
    InventoryApi $inventory_api,
    LoggerChannelFactoryInterface $loggerFactory,
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactory $configFactory,
    QueueFactory $queueFactory,
    Connection $connection
  ) {
    $this->dateFormatter = $dateFormatter;
    $this->time = $timeInterface;
    $this->inventoryApi = $inventory_api;
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
      $container->get('cecc_api.inventory_api'),
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('queue'),
      $container->get('database')
    );
  }

  private function isInventoryApiAvailable() {
    if (!$this->inventoryApi->apiActive) {
      $message = 'An API Key and service ID must be entered.';
      $this->messenger()->addError($message);
    }

    return $this->inventoryApi->apiActive;
  }

  /**
   * Gets all inventory.
   */
  public function refreshAllInventory() {

    if (!$this->isInventoryApiAvailable()) {
      return;
    }

    $query = $this->connection->select('commerce_product_variation_field_data', 'cpv')
      ->fields('cpv', ['id', 'field_cecc_warehouse_item_id'])
      ->where('cpv.cecc_check_stock_threshold >= cpv.field_cecc_stock');

    $count = $query->countQuery()->execute()->fetchField();

    if ($count < 1) {
      $this->logger->info('There are no products that need a stock refresh.');
      return FALSE;
    }

    $response = $this->inventoryApi->getAllInventory();

    if (empty($response)) {
      $this->logger->info('The inventory API returned an empty response.');
      return FALSE;
    }

    $ids = $query->execute()->fetchAll();

    $queue = $this->queueFactory->get('cecc_update_stock');

    foreach ($ids as $id) {
      $warehouse_record = array_search($id->field_cecc_warehouse_item_id, array_column($response, 'warehouse_item_id'));
      $item = [
        'id' => $id->id,
        'new_stock_value' => $warehouse_record['warehouse_stock_on_hand'],
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

    if (!$this->isInventoryApiAvailable()) {
      return;
    }

    $response = $this->inventoryApi->getSingleInventory($productVariation->get('field_cecc_warehouse_item_id')->value);

    if (empty($response)) {
      $this->messenger()->addError($this->t('%label failed to update. Check the error logs for more information.', [
        '%label' => $productVariation->getTitle(),
      ]));

      if ($this->inventoryApi->connectionError) {
        $this->logger->error('%label failed to update. @error', [
          '%label' => $productVariation->getTitle(),
          '@error' => $this->inventoryApi->connectionError,
        ]);
      }

      return;
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
      $this->messenger()->addError($this->t('%label failed to update. Check the error logs for more information.', [
        '%label' => $productVariation->getTitle(),
      ]));
    }
  }

}
