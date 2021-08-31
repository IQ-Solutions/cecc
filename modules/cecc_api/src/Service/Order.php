<?php

namespace Drupal\cecc_api\Service;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
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
   * Is order over limit.
   *
   * @var bool
   */
  public $isOverLimit = FALSE;

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

    //$profession = $order->get('field_profession')->value;
    $setting = $order->get('field_setting')->value;

    $cart = $this->getOrderItems($order);
    $profile = $this->getCustomerInformation($order);

    $orderArray = [
      'source_order_id' => $order->getOrderNumber(),
      'warehouse_organization_id' => $store->get('field_warehouse_organization_id')->value,
      'project_id' => $store->get('field_project_id')->value,
      'order_date' => date('c', $order->getCreatedTime()),
      'order_type' => 'web',
      'email' => $order->getEmail(),
      'complete' => $order->getState()->getId() == 'completed',
      'is_overlimit' => $this->isOverLimit ? 'true' : 'false',
      'overlimit_comments' => $this->t('@event_location@event_name@description', [
        '@event_location' => $order->get('field_event_location')->isEmpty() ? NULL : $order->get('field_event_location')->value . '|',
        '@event_name' => $order->get('field_event_name')->isEmpty() ? NULL : $order->get('field_event_name')->value . '|',
        '@description' => $order->get('field_cecc_over_limit_desc')->isEmpty() ? NULL : $order->get('field_cecc_over_limit_desc')->value,
      ]),
      'shipping_method' => '',
      'estimated_shipping_cost' => 0,
      'stripe_confirmation_code' => '',
      'use_shipping_account' => 'false',
      'shipping account_no' => '',
      'cart' => $cart,
      'shipping_address' => $profile['shipping_address'],
      'billing_address' => $profile['billing_address'],
      'customer_questions' => [
        //'profession' => $profession,
        'setting' => $setting,
      ],
    ];

    if ($this->config->get('debug') == 0) {
      try {
        /**
         * @todo Add a config value for the agency abbreviation.
         */
        $response = $this->httpClient->request('POST', 'api/orders/' . $agency, [
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
    }
    else {
      $file = file_save_data(json_encode($orderArray), 'public://testorder.json');
      $url = file_create_url($file->getFileUri());

      $this->logger->info('<a href="@file">File available at @file</a>', [
        '@file' => $url,
      ]);
      $this->messenger()->addStatus($this->t('<a href="@file">File available at @file</a>', [
        '@file' => $url,
      ]));
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
      $quantity = $orderItem->getQuantity();
      $overLimitValue = $purchasedEntity->get('field_cecc_order_limit');

      $orderArray = [
        'sku' => $purchasedEntity->get('sku')->value,
        'warehouse_item_id' => $purchasedEntity->get('field_cecc_warehouse_item_id')->value,
        'quantity' => (int) $orderItem->getQuantity(),
      ];

      $orderItems[] = $orderArray;

      if (!$this->isOverLimit) {
        $this->isOverLimit = $quantity > $overLimitValue;
      }
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
  private function getCustomerInformation(OrderInterface $order) {
    /** @var \Drupal\profile\Entity\ProfileInterface $shippingProfile */
    $shippingProfile = $this->getProfile($order, 'shipping');
    /** @var \Drupal\profile\Entity\ProfileInterface $billingProfile */
    $billingProfile = $this->getProfile($order, 'billing');

    $profile = [
      'shipping_address' => [],
      'billing_address' => [],
      'customer' => [],
    ];

    if ($shippingProfile) {
      $addressArray = $shippingProfile->get('address')->getValue()[0];
      $phone = $shippingProfile->get('field_phone_number')->value;
      $phoneExt = $shippingProfile->get('field_extension')->value;
      $profile['shipping_address']['first_name'] = $shippingProfile->get('field_name');
      $profile['shipping_address']['last_name'] = $addressArray['family_name'];
      $profile['shipping_address']['company_name'] = $addressArray['organization'];
      $profile['shipping_address']['address'] = $addressArray['address_line1'];
      $profile['shipping_address']['street2'] = $addressArray['address_line2'];
      $profile['shipping_address']['street3'] = '';
      $profile['shipping_address']['suite_no'] = '';
      $profile['shipping_address']['city'] = $addressArray['locality'];
      $profile['shipping_address']['state'] = $addressArray['administrative_area'];
      $profile['shipping_address']['zip'] = $addressArray['postal_code'];
      $profile['shipping_address']['country'] = $addressArray['country_code'];
      $profile['shipping_address']['phone'] = !empty($phone) ? $this->telephoneFormatter
        ->format($phone, 2, 'US') : NULL;
      $profile['shipping_address']['phone_ext'] = $phoneExt;
    }

    if ($billingProfile) {
      $addressArray = $billingProfile->get('address')->getValue()[0];
      $phone = $billingProfile->get('field_phone_number')->value;
      $phoneExt = $billingProfile->get('field_extension')->value;
      $profile['billing_address']['first_name'] = $billingProfile->get('field_name');
      $profile['billing_address']['last_name'] = $addressArray['family_name'];
      $profile['billing_address']['company_name'] = $addressArray['organization'];
      $profile['billing_address']['address'] = $addressArray['address_line1'];
      $profile['billing_address']['street2'] = $addressArray['address_line2'];
      $profile['billing_address']['street3'] = '';
      $profile['billing_address']['suite_no'] = '';
      $profile['billing_address']['city'] = $addressArray['locality'];
      $profile['billing_address']['state'] = $addressArray['administrative_area'];
      $profile['billing_address']['zip'] = $addressArray['postal_code'];
      $profile['billing_address']['country'] = $addressArray['country_code'];
      $profile['billing_address']['phone'] = !empty($phone) ? $this->telephoneFormatter
        ->format($phone, 2, 'US') : NULL;
      $profile['billing_address']['phone_ext'] = $phoneExt;
    }

    return $profile;
  }

  /**
   * Gets a customer profile.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $type
   *   The profile type.
   */
  public function getProfile(OrderInterface $order, $type) {
    $profiles = $order->collectProfiles();
    return isset($profiles[$type]) ? $profiles[$type] : NULL;
  }

}
