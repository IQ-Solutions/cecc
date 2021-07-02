<?php

namespace Drupal\cecc_api\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\cecc_api\Service\Stock;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for product restocking.
 */
class ConfirmProductRestockForm extends ContentEntityConfirmFormBase {

  /**
   * The stock api service.
   *
   * @var \Drupal\cecc_api\Service\Stock
   */
  protected $stock;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\cecc_api\Service\Stock $stock
   *   The stock service.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL,
    TimeInterface $time = NULL,
    Stock $stock) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);

    $this->stock = $stock;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('cecc_api.stock')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to refresh stock for @productName?', [
      '@productName' => $this->entity->getTitle(),
    ]);
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $productVariations = $this->entity->getVariations();

    foreach ($productVariations as $productVariation) {
      $this->stock->refreshInventory($productVariation);
    }

    $form_state->setRedirect('entity.commerce_product.edit_form', [
      'commerce_product' => $this->entity->id(),
    ]);
  }

  /**
   * {@inheritDoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('entity.commerce_product.edit_form', [
      'commerce_product' => $this->entity->id(),
    ]);
  }

  /**
   * {@inheritDoc}
   */
  public function getConfirmText() {
    return $this->t('Refresh Stock');
  }

  /**
   * {@inheritDoc}
   */
  public function getDescription() {
    $message = $this->t('You are retrieving the current stock for @product. Do you wish to continue?', [
      '@product' => $this->entity->getTitle(),
    ]);

    return $message;
  }

}
