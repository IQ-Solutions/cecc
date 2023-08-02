<?php

namespace Drupal\cecc_api\Service;

use Drupal\cecc_stock\Service\StockHelper;
use Drupal\commerce_price\CurrencyFormatter;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
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
   * The config factory object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The order data in array form.
   *
   * @var array
   */
  public $orderData = [
    'source_order_id' => '',
    'warehouse_organization_id' => '',
    'project_id' => '',
    'order_date' => '',
    'order_type' => 'web',
    'email' => '',
    'complete' => FALSE,
    'is_overlimit' => 'false',
    'event_location' => NULL,
    'event_name' => NULL,
    'overlimit_comments' => NULL,
    'shipping_method' => 'Free Shipping',
    'estimated_shipping_cost' => 0,
    'stripe_confirmation_code' => '',
    'use_shipping_account' => 'false',
    'shipping_account_no' => '',
    'cart' => NULL,
    'shipping_address' => NULL,
    'billing_address' => NULL,
    'customer_questions' => [
      'profession' => '',
      'setting' => '',
    ],
  ];

  /**
   * The order number.
   *
   * @var string
   */
  public $orderNumber;

  /**
   * The currency formatter service.
   *
   * @var \Drupal\commerce_price\CurrencyFormatter
   */
  public $currencyFormatter;

  /**
   * The currency formatter service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  public $moduleHandler;

  /**
   * Order object.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  public $order;

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
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(
    ClientFactory $http_client_factory,
    LoggerChannelFactoryInterface $loggerFactory,
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactory $configFactory,
    Formatter $telephone_formatter,
    CurrencyFormatter $currency_formatter,
    ModuleHandlerInterface $module_handler
  ) {
    $this->configFactory = $configFactory;
    $this->config = $configFactory->get('cecc_api.settings');
    $this->httpClient = $http_client_factory->fromOptions([
      'base_uri' => $this->config->get('base_api_url'),
    ]);
    $this->logger = $loggerFactory->get('cecc_api');
    $this->entityTypeManager = $entity_type_manager;
    $this->telephoneFormatter = $telephone_formatter;
    $this->currencyFormatter = $currency_formatter;
    $this->moduleHandler = $module_handler;
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
      $container->get('commerce_price.currency_formatter'),
      $container->get('module_handler')
    );
  }

  /**
   * Resets order data array.
   */
  private function resetOrderData() {
    $this->orderData = [
      'source_order_id' => '',
      'warehouse_organization_id' => '',
      'project_id' => '',
      'order_date' => '',
      'order_type' => 'web',
      'email' => '',
      'complete' => FALSE,
      'is_overlimit' => 'false',
      'event_location' => NULL,
      'event_name' => NULL,
      'overlimit_comments' => NULL,
      'shipping_method' => 'Free Shipping',
      'estimated_shipping_cost' => 0,
      'stripe_confirmation_code' => '',
      'use_shipping_account' => 'false',
      'shipping_account_no' => '',
      'cart' => NULL,
      'shipping_address' => NULL,
      'billing_address' => NULL,
      'customer_questions' => [
        'profession' => '',
        'setting' => '',
      ],
    ];
  }

  /**
   * Sets the order being sent.
   *
   * @param int $id
   *   The order id.
   */
  private function setOrder($id) {
    $this->order = $this->entityTypeManager->getStorage('commerce_order')
      ->load($id);
  }

  /**
   * Adds additional info data to data array.
   */
  private function loadAdditionalInformation() {
    $fields = $this->order->getFields();
    $field_name_conversion = [
      'field_setting' => 'setting',
      'field_order_occupation' => 'profession',
      'field_how_did_you_hear_about_us' => 'source',
    ];

    foreach ($fields as $field_name => $field) {
      $field_settings = $field->getSettings();

      if (isset($field_settings['allowed_values'])
        && isset($field_name_conversion[$field_name])) {
        $export_name = $field_name_conversion[$field_name];
        $this->orderData['customer_questions'][$export_name]
          = $this->order->get($field_name)->value;
      }
    }

    if ($this->order->hasField('field_profession')) {
      $this->orderData['customer_questions']['profession']
        = $this->order->get('field_profession')->value;
    }
  }

  /**
   * Gets items from an order.
   *
   * @return array
   *   Array of order items.
   */
  private function getOrderItems() {
    $orderItems = [];
    $orderItemsOverlimit = [];

    foreach ($this->order->getItems() as $orderItem) {
      $purchasedEntity = $orderItem->getPurchasedEntity();
      $quantity = (int) $orderItem->getQuantity();
      $sku = $purchasedEntity->get('sku')->value;
      $warehouse_item_id = $this->config->get('warehouse_item_id_field_name');

      $orderArray = [
        'sku' => $sku,
        'warehouse_item_id' => $purchasedEntity->get($warehouse_item_id)->value,
        'quantity' => $quantity,
      ];

      $orderItems[] = $orderArray;
      $order_config = $this->configFactory->get('cecc_order.settings');

      if ($order_config->get('process_over_limit')) {
        $isOverLimit = $this->checkOverLimit($quantity, $purchasedEntity);

        if ($isOverLimit) {
          $orderItemsOverlimit[] = $sku;
        }
      }
    }

    $this->isOverLimit = !empty($orderItemsOverlimit);

    return $orderItems;
  }

  /**
   * Checks if an order item is over limit.
   *
   * @param int $quantity
   *   The order item quantity.
   * @param \Drupal\commerce\PurchasableEntityInterface|null $purchasedEntity
   *   The purchaseable entity.
   *
   * @return bool
   *   Returns true if over limit, false if not.
   */
  private function checkOverLimit($quantity, $purchasedEntity) {
    $over_limit_field_name = StockHelper::getOrderLimitFieldName($purchasedEntity);
    $overLimitValue = !$purchasedEntity->get($over_limit_field_name)->isEmpty() ?
    (int) $purchasedEntity->get($over_limit_field_name)->value : 0;
    $isOverLimit = $overLimitValue > 0 ? $quantity > $overLimitValue : FALSE;

    return $isOverLimit;
  }

  /**
   * Set order profiles.
   */
  private function setOrderProfiles() {
    $customerProfiles = $this->order->collectProfiles();
    $this->setCustomerInformation($customerProfiles);
  }

  /**
   * Set the order number.
   */
  private function setOrderData() {
    $this->orderNumber = $this->order->getOrderNumber();
    $this->orderData['source_order_id'] = $this->order->getOrderNumber();
    $this->orderData['order_date'] = date('c', $this->order->getPlacedTime());
    $this->orderData['email'] = $this->order->getEmail();
    $this->orderData['complete'] = $this->order->getState()->getId() == 'completed';
  }

  /**
   * Set the order number.
   */
  private function setStoreData() {
    $store = $this->order->getStore();
    $this->orderData['warehouse_organization_id']
      = $store->get('field_warehouse_organization_id')->value;
    $this->orderData['project_id']
      = $store->get('field_project_id')->value;
  }

  /**
   * Set order item data.
   */
  private function setOrderItems() {
    $cart = $this->getOrderItems();

    $this->orderData['cart'] = $cart;
    $order_config = $this->configFactory->get('cecc_order.settings');

    if ($order_config->get('process_over_limit')) {
      $this->orderData['is_overlimit'] = $this->isOverLimit ? 'true' : 'false';
      $this->orderData['event_location'] =
        $this->order->get('field_event_location')->isEmpty()
        ? NULL : $this->order->get('field_event_location')->value;
      $this->orderData['event_name'] =
        $this->order->get('field_event_name')->isEmpty()
        ? NULL : $this->order->get('field_event_name')->value;
      $this->orderData['overlimit_comments'] =
        $this->order->get('field_cecc_over_limit_desc')->isEmpty()
        ? NULL : $this->order->get('field_cecc_over_limit_desc')->value;
    }
  }

  /**
   * Sets the order payment information.
   */
  private function setPaymentInfo() {
    /** @var \Drupal\commerce_payment\PaymentStorageInterface $commercePaymentStorage */
    $commercePaymentStorage = $this->entityTypeManager->getStorage('commerce_payment');
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface[] $payments */
    $payments = $commercePaymentStorage->loadMultipleByOrder($this->order);

    foreach ($payments as $payment) {
      if (empty($payment)) {
        continue;
      }

      $paymentGateway = $payment->getPaymentGateway()->getPluginId();
      $paymentMethod = $payment->getPaymentMethod();

      if ($paymentGateway == 'stripe') {
        $this->orderData['stripe_confirmation_code'] = $paymentMethod->getRemoteId();
      }
      elseif ($paymentGateway == 'cecc_shipping_account') {
        $this->orderData['use_shipping_account'] = 'true';
        $this->orderData['shipping_account_no'] = $paymentMethod->label();
      }

    }
  }

  /**
   * Sets shipping cost and method.
   */
  private function setShippingInfo() {
    if ($this->order->hasField('shipments')) {
      /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipments */
      $shipments = $this->order->get('shipments')->entity;

      $shippingMethod = $shipments->getShippingMethod();
      $price = $shipments->getAmount();
      $this->orderData['shipping_method'] = $shippingMethod->label();
      $this->orderData['estimated_shipping_cost'] = $this->currencyFormatter
        ->format($price->getNumber(), $price->getCurrencyCode());
    }
  }

  /**
   * Populates order array.
   *
   * @param int $id
   *   The order ID.
   */
  private function collectOrderData($id) {

    $this->setOrder($id);

    if (is_null($this->order)) {
      $this->logger->warning('Order does not exist: @id', ['@id', $id]);
      return self::ORDER_DOES_NOT_EXIST;
    }

    $this->setOrderData();
    $this->setStoreData();
    $this->setOrderItems();

    $this->loadAdditionalInformation();

    if ($this->moduleHandler->moduleExists('commerce_payment')) {
      $this->setPaymentInfo();
    }

    if ($this->moduleHandler->moduleExists('commerce_shipping')) {
      $this->setShippingInfo();
    }

    $this->setOrderProfiles();

  }

  /**
   * Sends Json view of order data.
   *
   * @param int $id
   *   The order ID.
   */
  public function viewOrderAsJson($id) {
    $this->resetOrderData();
    $this->collectOrderData($id);
    $response = new CacheableJsonResponse($this->orderData);

    return $response;
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

    $this->resetOrderData();
    $this->collectOrderData($id);
    $orderDataJson = json_encode($this->orderData);

    try {
      $response = $this->httpClient->request('POST', 'api/orders/' . $agency, [
        'headers' => [
          'IQ_Client_Key' => $apiKey,
          'Content-Type' => 'application/json',
        ],
        'body' => $orderDataJson,
      ]);

      if ($response->getStatusCode() != 200) {
        $message = $this->t('The service failed with the following error: %error', [
          '%error' => $response['message'],
          '%response' => $orderDataJson,
        ]);

        $this->logger->error($message);
        return self::API_CONNECTION_ERROR;
      }
    }
    catch (GuzzleException $error) {
      $log_message =[
        '@error' => $error->getMessage(),
        '@response' => $orderDataJson,
      ];

      $message = '@error | @response';

      if ($this->config->get('debug') == 0) {
        $message = '@error | @response';
      }

      $this->logger->error($message, $log_message);

      return self::INTERNAL_CONNECTION_ERROR;
    }

    return self::ORDER_SENT;
  }

  /**
   * Set order shipping information.
   *
   * @param \Drupal\profile\Entity\ProfileInterface[] $customerProfiles
   *   Customer profiles.
   */
  private function setCustomerInformation(array $customerProfiles) {
    foreach ($customerProfiles as $type => $profile) {
      $addressArray = $profile->get('address')->getValue()[0];
      $phone = $profile->hasField('field_phone') ?
        $profile->get('field_phone')->value : $profile->get('field_phone_number')->value;
      $phoneExt = $profile->get('field_extension')->value;
      $type = $type == 'cecc_shipping' ? 'shipping' : $type;
      $this->orderData[$type . '_address']['first_name']
        = $profile->get('field_first_name')->isEmpty()?
          $addressArray['given_name'] : $profile->get('field_first_name')->value;
      $this->orderData[$type . '_address']['last_name']
        = $profile->get('field_last_name')->isEmpty()?
          $addressArray['family_name'] : $profile->get('field_last_name')->value;
      $this->orderData[$type . '_address']['company_name']
        = $profile->get('field_organization')->isEmpty()?
          $addressArray['organization'] : $profile->get('field_organization')->value;
      $this->orderData[$type . '_address']['address']
        = $addressArray['address_line1'];
      $this->orderData[$type . '_address']['street2']
        = $addressArray['address_line2'];
      $this->orderData[$type . '_address']['street3'] = '';
      $this->orderData[$type . '_address']['suite_no'] = '';
      $this->orderData[$type . '_address']['city'] = $addressArray['locality'];
      $this->orderData[$type . '_address']['state']
        = $addressArray['administrative_area'];
      $this->orderData[$type . '_address']['zip']
        = !empty($addressArray['postal_code']) ? $addressArray['postal_code'] : '00000';
      $this->orderData[$type . '_address']['country']
        = $addressArray['country_code'];
      $this->orderData[$type . '_address']['phone'] = !empty($phone)
        ? $this->telephoneFormatter->format($phone, 2, 'US') : NULL;
      $this->orderData[$type . '_address']['phone_ext'] = $phoneExt;
    }

    if ($this->config->get('combine_billing_shipping') == 1) {
      $this->orderData['shipping_address'] = $this->orderData['billing_address'];
      unset($this->orderData['billing_address']);
    }

  }

}
