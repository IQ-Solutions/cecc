<?php

namespace Drupal\cecc_api\Plugin\QueueWorker;

use Drupal\cecc_stock\Event\RestockEvent;
use Drupal\cecc_stock\Service\StockValidation;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\http_client_manager\HttpClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Stock Validation service.
   *
   * @var \Drupal\cecc_stock\Service\StockValidation
   */
  protected $stockValidation;

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
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher service.
   */
  public function __construct(
    HttpClientInterface $http_client,
    ConfigFactory $configFactory,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $loggerFactory,
    EventDispatcherInterface $event_dispatcher,
    StockValidation $stockValidation) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $loggerFactory->get('cecc_api');
    $this->config = $configFactory->get('cecc_api.settings');
    $this->httpClient = $http_client;
    $this->eventDispatcher = $event_dispatcher;
    $this->stockValidation = $stockValidation;
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
      $container->get('logger.factory'),
      $container->get('event_dispatcher'),
      $container->get('cecc_stock.stock_validation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($item) {

    $productVariationIds = $this->entityTypeManager->getStorage('commerce_product_variation')
      ->getQuery()
      ->condition('field_cecc_warehouse_item_id', $item['id'])
      ->execute();

    if (empty($productVariationIds)) {
      return FALSE;
    }

    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $productVariation */
    $productVariation = $this->entityTypeManager->getStorage('commerce_product_variation')
      ->load(reset($productVariationIds));

    if (is_null($productVariation)) {
      $this->logger->warning('Product does not exist: @id', ['@id' => $item['id']]);
      return FALSE;
    }

    $previouslyInStock = $this->stockValidation->checkProductStock($productVariation);

    $productVariation->set('field_cecc_stock', $item['new_stock_value']);
    $productVariation->set('field_awaiting_stock_refresh', FALSE);

    try {
      $productVariation->save();

      $inStock = $this->stockValidation->checkProductStock($productVariation);

      if (!$previouslyInStock && $inStock) {
        $restockEvent = new RestockEvent($productVariation);
        $this->eventDispatcher->dispatch($restockEvent, RestockEvent::CECC_PRODUCT_VARIATION_RESTOCK);
      }

      $this->logger->info('Stock for %label has been refreshed to %level', [
        '%label' => $productVariation->getTitle(),
        '%level' => $productVariation->get('field_cecc_stock')->value,
      ]);
    }
    catch (EntityStorageException $error) {
      $this->logger->error($error->getMessage());
      throw new RequeueException($error->getMessage());
    }
  }

}
