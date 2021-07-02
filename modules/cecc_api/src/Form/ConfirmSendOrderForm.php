<?php

namespace Drupal\cecc_api\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\cecc_api\Service\Order;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for product restocking.
 */
class ConfirmSendOrderForm extends ContentEntityConfirmFormBase {

  /**
   * The stock api service.
   *
   * @var \Drupal\cecc_api\Service\order
   */
  protected $orderApi;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\cecc_api\Service\order $order_api
   *   The order service.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL,
    TimeInterface $time = NULL,
    Order $order_api) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);

    $this->orderApi = $order_api;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('cecc_api.order')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to send the order @order?', [
      '@order' => $this->entity->getOrderNumber(),
    ]);
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $orderStatus = $this->orderApi->sendOrder($this->entity->id());


    switch ($orderStatus) {
      case $this->orderApi::ORDER_DOES_NOT_EXIST:
        $message = 'Order does not exist.';
        $this->messenger()->addError($message);
        $this->logger('cecc_api')->error($message);
        break;

      case $this->orderApi::API_CONNECTION_ERROR:
        $message = 'Could not connect to the API. Contact API support.';
        $this->messenger()->addError($message);
        $this->logger('cecc_api')->error($message);
        break;

      case $this->orderApi::INTERNAL_CONNECTION_ERROR:
        $message = 'Drupal could not load API connection service. Contact Drupal support.';
        $this->messenger()->addError($message);
        $this->logger('cecc_api')->error($message);
        break;

      case $this->orderApi::API_NOT_CONFIGURED:
        $message = 'API is not configured.';
        $this->messenger()->addError($message);
        $this->logger('cecc_api')->error($message);
        break;

      default:
        $message = $this->t('Order :order_number was sent.', [
          ':order_number' => $this->entity->getOrderNumber(),
        ]);
        $this->messenger()->addStatus($message);
        $this->logger('cecc_api')->info($message);
        break;
    }

    $form_state->setRedirect('entity.commerce_order.edit_form', [
      'commerce_order' => $this->entity->id(),
    ]);
  }

  /**
   * {@inheritDoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('entity.commerce_order.edit_form', [
      'commerce_order' => $this->entity->id(),
    ]);
  }

  /**
   * {@inheritDoc}
   */
  public function getConfirmText() {
    return $this->t('Send Order to API');
  }

  /**
   * {@inheritDoc}
   */
  public function getDescription() {
    $message = $this->t('You are preparing to send an order @order. Do you wish to continue?', [
      '@order' => $this->entity->getOrderNumber(),
    ]);

    return $message;
  }

}
