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
 * Provides a order state resource.
 *
 * @RestResource(
 *   id = "cecc_order_state_resource",
 *   label = @Translation("Commerce order state resource"),
 *   uri_paths = {
 *     "canonical" = "/catalog_api/order_state"
 *   }
 * )
 */
class OrderStateResource extends ResourceBase {

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
   * Responds to Get requests.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get() {
    $response = ['code' => 200];
    $orderId = Xss::filter($this->request->get('order_id'));
    $status = Xss::filter($this->request->get('status'));

    $build = [
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    if (empty($orderId) && empty($status)) {
      $response['code'] = 500;
      $response['message'] = $this->t('Empty payload recieved.');
      return (new ResourceResponse($response, $response['code']))->addCacheableDependency($build);
    }

    if (empty($orderId)) {
      $response['code'] = 500;
      $response['message'] = $this->t('No order id recieved.');
      return (new ResourceResponse($response, $response['code']))->addCacheableDependency($build);
    }

    if (empty($status)) {
      $response['code'] = 500;
      $response['message'] = $this->t('No status recieved.');
      return (new ResourceResponse($response, $response['code']))->addCacheableDependency($build);
    }

    $orderStorage = $this->entityTypeMananger->getStorage('commerce_order');

    /** @var \Drupal\commerce_order\Entity\OrderInterface[] $orders */
    $orders = $orderStorage->loadByProperties([
      'order_number' => $orderId,
    ]);

    if (!empty($orders)) {
      $order = reset($orders);
      $orderState = $order->getState();
      $orderStateTransitions = $orderState->getTransitions();
      $stateChange = FALSE;

      switch ($status) {
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
          ':status' => $status,
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

  /**
   * {@inheritdoc}
   */
  public function getProfile(OrderInterface $order) {
    $profiles = $order->collectProfiles();
    return isset($profiles['billing']) ? $profiles['billing'] : NULL;
  }

}
