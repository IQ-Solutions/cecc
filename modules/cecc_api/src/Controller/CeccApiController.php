<?php

namespace Drupal\cecc_api\Controller;

use Drupal\cecc_api\Service\Order;
use Drupal\commerce_order\Entity\Order as EntityOrder;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CeccApiController extends ControllerBase {

  /**
   * The order service.
   *
   * @var \Drupal\cecc_api\Service\Order
   */
  private $orderService;

  public function __construct(Order $order_service) {
    $this->orderService = $order_service;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cecc_api.order')
    );
  }

  public function showOrderJson(EntityOrder $commerce_order) {
    return $this->orderService->viewOrderAsJson($commerce_order->id());
  }
}