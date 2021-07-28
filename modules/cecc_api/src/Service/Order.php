<?php

namespace Drupal\cecc_api\Service;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\telephone_formatter\Formatter;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Order API service.
 */
class Order implements ContainerInjectionInterface {
  use StringTranslationTrait;
  use MessengerTrait;

  /**
   * Value sent if API is not configured.
   *
   * @var int
   */
  const API_NOT_CONFIGURED = 5;
  const ORDER_DOES_NOT_EXIST = 1;
  const API_CONNECTION_ERROR = 2;
  const INTERNAL_CONNECTION_ERROR = 4;
  const ORDER_SENT = 0;

  /**
   * Guzzle\Client instance.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

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
   * Service config and DI.
   *
   * @param \GuzzleHttp\Drupal\Core\Http\ClientFactory $http_client_factory
   *   Http client manager client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Drupal logger service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager service.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   The config factory service.
   * @param \Drupal\telephone_formatter\Formatter $telephone_formatter
   *   The telephone number formatter service.
   */
  public function __construct(
    ClientFactory $http_client_factory,
    LoggerChannelFactoryInterface $loggerFactory,
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactory $configFactory,
    Formatter $telephone_formatter
  ) {
    $this->config = $configFactory->get('cecc_api.settings');
    $this->httpClient = $http_client_factory->fromOptions([
      'base_uri' => $this->config->get('base_api_url'),
    ]);
    $this->logger = $loggerFactory->get('cecc_api');
    $this->entityTypeManager = $entity_type_manager;
    $this->telephoneFormatter = $telephone_formatter;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client_factory'),
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('telephone_formatter.formatter')
    );
  }

  /**
   * Sends an order through the api.
   *
   * @param int $id
   *   The order ID.
   */
  public function sendOrder($id) {
    $agency = $this->config->get('agency');
    $apiKey = $this->config->get('api_key');

    if (empty($apiKey) || empty($agency)) {
      $message = 'An API Key and service ID must be entered.';

      $this->logger->error($message);

      return self::API_NOT_CONFIGURED;
    }

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $this->entityTypeManager->getStorage('commerce_order')
      ->load($id);

    if (is_null($order)) {
      $this->logger->warning('Order does not exist: @id', ['@id', $id]);
      return self::ORDER_DOES_NOT_EXIST;
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
      /**
       * @todo Add a config value for the agency abbreviation.
       */
      $response = $this->httpClient->request('POST', 'api/orders/NINDS', [
        'headers' => [
          'IQ_Client_Key' => $apiKey,
          'Content-Type' => 'application/json',
        ],
        'body' => json_encode($orderArray),
      ]);

      if ($response->getStatusCode() != 200) {
        $message = $this->t('The service failed with the following error: %error', [
          '%error' => $response['message'],
          '%response' => json_encode($orderArray),
        ]);

        $this->logger->error($message);
        return self::API_CONNECTION_ERROR;
      }
    }
    catch (GuzzleException $error) {
      $this->logger->error('@error | @response', [
        '@error' => $error->getMessage(),
        '@response' => json_encode($orderArray),
      ]);

      return self::INTERNAL_CONNECTION_ERROR;
    }

    return self::ORDER_SENT;
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
        'warehouse_item_id' => $purchasedEntity->get('field_cecc_warehouse_item_id')->value,
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
