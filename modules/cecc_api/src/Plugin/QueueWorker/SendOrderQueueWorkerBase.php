<?php

namespace Drupal\cecc_api\Plugin\QueueWorker;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\cecc_api\Service\Order;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes items for updating stocking value.
 */
class SendOrderQueueWorkerBase extends QueueWorkerBase implements ContainerFactoryPluginInterface {
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
   * Send order api.
   *
   * @var \Drupal\cecc_api\Service\Order
   */
  protected $orderApi;

  /**
   * The shipping order manager service.
   *
   * @var \Drupal\commerce_shipping\ShippingOrderManagerInterface
   */
  protected $shippingOrderManager;

  /**
   * Queueworker Construct.
   *
   * @param \Drupal\cecc_api\Service\Order $order_api
   *   Order API service.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Drupal logger service.
   */
  public function __construct(
    Order $order_api,
    ConfigFactory $configFactory,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $loggerFactory) {
    $this->orderApi = $order_api;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $loggerFactory->get('cecc_api');
    $this->config = $configFactory->get('cecc_api.settings');
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
      $container->get('cecc_api.order'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($item) {
    $orderStatus = $this->orderApi->sendOrder($item['id']);

    switch ($orderStatus) {
      case $this->orderApi::ORDER_DOES_NOT_EXIST:
        return FALSE;

      case $this->orderApi::API_CONNECTION_ERROR:
      case $this->orderApi::INTERNAL_CONNECTION_ERROR:
      case $this->orderApi::API_NOT_CONFIGURED:
        throw new SuspendQueueException('The service failed to connect. Please check the error log for more information. Item requeued.');

    }
  }

}
