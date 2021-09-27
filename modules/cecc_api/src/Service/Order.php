<?php

namespace Drupal\cecc_api\Service;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\CurrencyFormatter;
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
   * The order data in array form.
   *
   * @var array
   */
  public $orderData;

  /**
   * The currency formatter service.
   *
   * @var \Drupal\commerce_price\CurrencyFormatter
   */
  public $currencyFormatter;

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
   * @param \Drupal\commerce_price\CurrencyFormatter $currency_formatter
   *   The currency formatter service.
   */
  public function __construct(
    ClientFactory $http_client_factory,
    LoggerChannelFactoryInterface $loggerFactory,
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactory $configFactory,
    Formatter $telephone_formatter,
    CurrencyFormatter $currency_formatter
  ) {
    $this->config = $configFactory->get('cecc_api.settings');
    $this->httpClient = $http_client_factory->fromOptions([
      'base_uri' => $this->config->get('base_api_url'),
    ]);
    $this->logger = $loggerFactory->get('cecc_api');
    $this->entityTypeManager = $entity_type_manager;
    $this->telephoneFormatter = $telephone_formatter;
    $this->currencyFormatter = $currency_formatter;
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
      $container->get('telephone_formatter.formatter'),
      $container->get('commerce_price.currency_formatter')
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
    $customerProfiles = $order->collectProfiles();
    $profile = $this->getCustomerInformation($customerProfiles);
    $profession = $profile['shipping_address']['profession'];

    unset($profile['shipping_address']['profession'], $profile['billing_address']['profession']);

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipments */
    $shipments = $order->get('shipments')->entity;

    $shippingMethod = $shipments->getShippingMethod();
    $price = $shipments->getAmount();
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface[] $payments */
    $payments = $this->entityTypeManager->getStorage('commerce_payment')
      ->loadMultipleByOrder($order);
    $paymentId = !empty($payments) ? reset($payments) : '';

    $this->orderData = [
      'source_order_id' => $order->getOrderNumber(),
      'warehouse_organization_id' => $store->get('field_warehouse_organization_id')->value,
      'project_id' => $store->get('field_project_id')->value,
      'order_date' => date('c', $order->getCreatedTime()),
      'order_type' => 'web',
      'email' => $order->getEmail(),
      'complete' => $order->getState()->getId() == 'completed',
      'is_overlimit' => $this->isOverLimit ? 'true' : 'false',
      'event_location' => $order->get('field_event_location')->isEmpty() ? NULL : $order->get('field_event_location')->value,
      'event_name' => $order->get('field_event_name')->isEmpty() ? NULL : $order->get('field_event_name')->value,
      'overlimit_comments' => $order->get('field_cecc_over_limit_desc')->isEmpty() ? NULL : $order->get('field_cecc_over_limit_desc')->value,
      'shipping_method' => $shippingMethod->label(),
      'estimated_shipping_cost' => $this->currencyFormatter->format($price->getNumber(), $price->getCurrencyCode()),
      'stripe_confirmation_code' => $paymentId,
      'use_shipping_account' => 'false',
      'shipping account_no' => '',
      'cart' => $cart,
      'shipping_address' => $profile['shipping_address'],
      'billing_address' => $profile['billing_address'],
      'customer_questions' => [
        'profession' => $profession,
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
          'body' => json_encode($this->orderData),
        ]);

        if ($response->getStatusCode() != 200) {
          $message = $this->t('The service failed with the following error: %error', [
            '%error' => $response['message'],
            '%response' => json_encode($this->orderData),
          ]);

          $this->logger->error($message);
          return self::API_CONNECTION_ERROR;
        }
      }
      catch (GuzzleException $error) {
        $this->logger->error('@error | @response', [
          '@error' => $error->getMessage(),
          '@response' => json_encode($this->orderData),
        ]);

        return self::INTERNAL_CONNECTION_ERROR;
      }
    }
    else {
      $file = file_save_data(json_encode($this->orderData), 'public://testorder.json');
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
      $overLimitValue = $purchasedEntity->get('field_cecc_order_limit')->value;

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

   * @param \Drupal\profile\Entity\ProfileInterface[] $customerProfiles
   *   Customer profiles.
   *
   * @return array
   *   The address information in array format.
   */
  private function getCustomerInformation(array $customerProfiles) {
    $profiles = [];

    foreach ($customerProfiles as $type => $profile) {
      $addressArray = $profile->get('address')->getValue()[0];
      $phone = $profile->get('field_phone_number')->value;
      $phoneExt = $profile->get('field_extension')->value;
      $profiles[$type . '_address']['first_name'] = $profile->get('field_name')[0]->given;
      $profiles[$type . '_address']['last_name'] = $profile->get('field_name')[0]->family;
      $profiles[$type . '_address']['company_name'] = $profile->get('field_organization')->value;
      $profiles[$type . '_address']['address'] = $addressArray['address_line1'];
      $profiles[$type . '_address']['street2'] = $addressArray['address_line2'];
      $profiles[$type . '_address']['street3'] = '';
      $profiles[$type . '_address']['suite_no'] = '';
      $profiles[$type . '_address']['city'] = $addressArray['locality'];
      $profiles[$type . '_address']['state'] = $addressArray['administrative_area'];
      $profiles[$type . '_address']['zip'] = $addressArray['postal_code'];
      $profiles[$type . '_address']['country'] = $addressArray['country_code'];
      $profiles[$type . '_address']['phone'] = !empty($phone) ? $this->telephoneFormatter
        ->format($phone, 2, 'US') : NULL;
      $profiles[$type . '_address']['phone_ext'] = $phoneExt;
      $profiles[$type . '_address']['profession'] = $profile->get('field_occupation')->value;
    }

    return $profiles;
  }

}
