<?php

namespace Drupal\cecc_api\Plugin\rest\resource;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_store\SelectStoreTrait;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\telephone_formatter\Formatter;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a publication resource.
 *
 * @RestResource(
 *   id = "cecc_order_resource",
 *   label = @Translation("Commerce order publication resource"),
 *   uri_paths = {
 *     "canonical" = "/catalog_api/order",
 *     "create" = "/catalog_api/order/update"
 *   }
 * )
 */
class OrderResource extends ResourceBase {

  use SelectStoreTrait;

  /**
   * EntityType Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeMananger;

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The request object that contains the parameters.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The shipping order manager service.
   *
   * @var \Drupal\telephone_formatter\Formatter
   */
  protected $telephoneFormatter;

  /**
   * Constructs a new object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager object.
   * @param \\Drupal\telephone_formatter\Formatter $telephoneFormatter
   *   The telephone number formatter service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user,
    Request $request,
    EntityTypeManagerInterface $entity_type_manager,
    Formatter $telephoneFormatter
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->request = $request;
    $this->currentUser = $current_user;
    $this->entityTypeMananger = $entity_type_manager;
    $this->telephoneFormatter = $telephoneFormatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('cecc_api'),
      $container->get('current_user'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('entity_type.manager'),
      $container->get('telephone_formatter.formatter')
    );
  }

  /**
   * Responses to entity get request.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The resource response.
   */
  public function get() {
    $response = ['code' => 200];

    $query = $this->entityTypeMananger->getStorage('commerce_order')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'cecc_publication');

    $orderIds = $query->execute();

    /** @var \Drupal\commerce_order\Entity\OrderInterface[] $orders */
    $orders = $this->entityTypeMananger->getStorage('commerce_order')
      ->loadMultiple($orderIds);

    foreach ($orders as $order) {
      $cart = $this->getOrderItems($order);
      $profile = $this->getShippingInformation($order);

      $orderArray = [
        'source_order_id' => $order->getOrderNumber(),
        'warehouse_organization_id' => NULL,
        'project_id' => NULL,
        'order_date' => date('c', $order->getCreatedTime()),
        'order_type' => 'web',
        'email' => $order->getEmail(),
        'complete' => $order->getState()->getId() == 'completed',
        'cart' => $cart,
        'shipping_address' => $profile['address'],
        'customer_questions' => $profile['customer'],
      ];

      $response['orders'][] = $orderArray;
    }

    $build = [
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    return (new ResourceResponse($response))->addCacheableDependency($build);
  }

  /**
   * Responds to POST requests.
   *
   * Creates a new node.
   *
   * @param mixed $data
   *   Data to create the node.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function post($data) {
    $response = ['code' => 200];

    $build = [
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    if (empty($data)) {
      $response['code'] = 500;
      $response['message'] = $this->t('Empty payload recieved.');
      return (new ResourceResponse($response, $response['code']))->addCacheableDependency($build);
    }

    if (!isset($data['order_id'])) {
      $response['code'] = 500;
      $response['message'] = $this->t('No order id recieved.');
      return (new ResourceResponse($response, $response['code']))->addCacheableDependency($build);
    }

    $orderStorage = $this->entityTypeMananger->getStorage('commerce_order');

    /** @var \Drupal\commerce_order\Entity\OrderInterface[] $orders */
    $orders = $orderStorage->loadByProperties([
      'order_number' => Xss::filter($data['order_id']),
    ]);

    if (!empty($orders)) {
      $order = reset($orders);
      $orderState = $order->getState();
      $orderStateTransitions = $orderState->getTransitions();
      $stateChange = FALSE;

      switch ($data['status']) {
        case 'cancelled':
          $stateChange = 'cancel';
          break;

        case 'shipped':
          $stateChange = 'fulfill';
          break;
      }

      if (!isset($orderStateTransitions[$stateChange])) {
        $response['code'] = 400;
        $response['message'] = $this->t('This order cannot be transitioned to :status. It is currently :currentState', [
          ':status' => $data['status'],
          ':currentState' => $orderState->getLabel(),
        ]);
      }
      else {
        if ($stateChange !== FALSE) {

          $orderState->applyTransitionById($stateChange);
          try {
            $order->save();
            $response['message'] = $this->t('Order updated.');
          }
          catch (EntityStorageException $e) {
            $this->logger->error($e->getMessage());
            $response['code'] = 500;
            $response['message'] = $this->t('Order failed to update.');
          }
        }
        else {
          $response['code'] = 304;
          $response['message'] = $this->t('Order status not changed.');
        }
      }
    }
    else {
      $response['code'] = 404;
      $response['message'] = $this->t('No order found.');
    }

    return (new ResourceResponse($response, $response['code']))->addCacheableDependency($build);
  }

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
      $profession = $shippingProfile->get('field_profession')->value;
      $setting = $shippingProfile->get('field_setting')->value;
      $profile['address']['first_name'] = $addressArray['given_name'];
      $profile['address']['last_name'] = $addressArray['family_name'];
      $profile['address']['company_name'] = $addressArray['company'];
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
      $profile['customer']['profession'] = $profession;
      $profile['customer']['setting'] = $setting;
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
