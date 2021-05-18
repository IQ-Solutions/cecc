<?php

namespace Drupal\po_api\Plugin\QueueWorker;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\ShippingOrderManagerInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\telephone_formatter\Formatter;
use GuzzleHttp\Exception\GuzzleException;
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
   * Guzzle\Client instance.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The shipping order manager service.
   *
   * @var \Drupal\commerce_shipping\ShippingOrderManagerInterface
   */
  protected $shippingOrderManager;

  /**
   * The shipping order manager service.
   *
   * @var \Drupal\telephone_formatter\Formatter
   */
  protected $telephoneFormatter;

  /**
   * Queueworker Construct.
   *
   * @param \GuzzleHttp\Drupal\Core\Http\ClientFactory $http_client_factory
   *   Http client manager client.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Drupal logger service.
   * @param \Drupal\telephone_formatter\Formatter $telephone_formatter
   *   The telephone number formatter service.
   */
  public function __construct(
    ClientFactory $http_client_factory,
    ConfigFactory $configFactory,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $loggerFactory,
    Formatter $telephone_formatter) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $loggerFactory->get('po_api');
    $this->config = $configFactory->get('po_api.settings');
    $this->httpClient = $http_client_factory->fromOptions([
      'base_uri' => 'https://dev-order-apis.azurewebsites.net/',
    ]);
    $this->telephoneFormatter = $telephone_formatter;
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
      $container->get('http_client_factory'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('telephone_formatter.formatter')
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

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $this->entityTypeManager->getStorage('commerce_order')
      ->load($item['id']);

    if (is_null($order)) {
      $this->logger->warning('Order does not exist: @id', ['@id', $item['id']]);
      return FALSE;
    }

    /** @var \Drupal\commerce_store\Entity\StoreInterface $store */
    $store = $this->entityTypeManager->getStorage('commerce_store')
      ->loadDefault();

    $profession = $order->get('field_profession')->value;
    $setting = $order->get('field_setting')->value;

    $cart = $this->getOrderItems($order);
    $profile = $this->getShippingInformation($order);

    $orderArray = [
      'source_order_id' => $order->getOrderNumber(),
      'warehouse_organization_id' => $store->get('field_warehouse_organization_id')->value,
      'project_id' => $store->get('field_project_id')->value,
      'order_date' => date('c', $order->getCreatedTime()),
      'order_type' => 'web',
      'email' => $order->getEmail(),
      'complete' => $order->getState()->getId() == 'completed',
      'cart' => $cart,
      'shipping_address' => $profile['address'],
      'customer_questions' => [
        'profession' => $profession,
        'setting' => $setting,
      ],
    ];

    try {
      $response = $this->httpClient->request('POST', 'api/orders/NIDCR', [
        'headers' => [
          'IQ_Client_Key' => $apiKey,
          'Content-Type' => 'application/json',
        ],
        'body' => json_encode($orderArray),
      ]);

      if ($response->getStatusCode() != 200) {
        $message = $this->t('The service failed with the follow error: %error', [
          '%error' => $response['message'],
          '%response' => json_encode($orderArray),
        ]);

        $this->logger->error($message);
        throw new SuspendQueueException($message);
      }
    }
    catch (GuzzleException $error) {
      $this->logger->error('@error | @response', [
        '@error' => $error->getMessage(),
        '@response' => json_encode($orderArray),
      ]);
      throw new SuspendQueueException($error->getMessage());
    }
  }

  /**
   * Gets items from an order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order entity.
   *
   * @return array
   *   Array of order items.
   */
  private function getOrderItems(OrderInterface $order) {
    $orderItems = [];

    foreach ($order->getItems() as $orderItem) {
      $purchasedEntity = $orderItem->getPurchasedEntity();

      $orderArray = [
        'sku' => $purchasedEntity->get('sku')->value,
        'warehouse_item_id' => $purchasedEntity->get('field_warehouse_item_id')->value,
        'quantity' => (int) $orderItem->getQuantity(),
      ];

      $orderItems[] = $orderArray;
    }

    return $orderItems;
  }

  /**
   * Get order shipping information.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order entity.
   *
   * @return array
   *   The address information in array format.
   */
  private function getShippingInformation(OrderInterface $order) {
    /** @var \Drupal\profile\Entity\ProfileInterface $shippingProfile */
    $shippingProfile = $this->getProfile($order);

    $profile = [
      'address' => [],
      'customer' => [],
    ];

    if ($shippingProfile) {
      $addressArray = $shippingProfile->get('address')->getValue()[0];
      $phone = $shippingProfile->get('field_phone')->value;
      $phoneExt = $shippingProfile->get('field_extension')->value;
      $profile['address']['first_name'] = $addressArray['given_name'];
      $profile['address']['last_name'] = $addressArray['family_name'];
      $profile['address']['company_name'] = $addressArray['organization'];
      $profile['address']['address'] = $addressArray['address_line1'];
      $profile['address']['street2'] = $addressArray['address_line2'];
      $profile['address']['street3'] = '';
      $profile['address']['suite_no'] = '';
      $profile['address']['city'] = $addressArray['locality'];
      $profile['address']['state'] = $addressArray['administrative_area'];
      $profile['address']['zip'] = $addressArray['postal_code'];
      $profile['address']['country'] = $addressArray['country_code'];
      $profile['address']['phone'] = !empty($phone) ? $this->telephoneFormatter
        ->format($phone, 2, 'US') : NULL;
      $profile['address']['phone_ext'] = $phoneExt;
    }

    return $profile;
  }

  /**
   * {@inheritdoc}
   */
  public function getProfile(OrderInterface $order) {
    $profiles = $order->collectProfiles();
    return isset($profiles['billing']) ? $profiles['billing'] : NULL;
  }

}
