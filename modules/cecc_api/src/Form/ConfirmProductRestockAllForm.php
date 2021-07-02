<?php

namespace Drupal\cecc_api\Form;

use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Url;
use Drupal\cecc_api\Service\Stock;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for product restocking.
 */
class ConfirmProductRestockAllForm extends ConfirmFormBase {

  /**
   * The stock api service.
   *
   * @var \Drupal\cecc_api\Service\Stock
   */
  protected $stock;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\cecc_api\Service\Stock $stock
   *   The stock service.
   */
  public function __construct(
    Stock $stock) {

    $this->stock = $stock;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cecc_api.stock')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'confirm_restock_all_form';
  }

  /**
   * {@inheritDoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to refresh all stock?');
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch = [
      'title' => $this->t('Refresh All Product Inventory'),
      'operations' => [
        [
          '\Drupal\cecc_api\Form\ConfirmProductRestockAllForm::batchProcessInventory',
          [],
        ],
      ],
      'finished' => '\Drupal\cecc_api\Form\ConfirmProductRestockAllForm::finishedInventoryRefresh',
      'init_message' => $this->t('Loading products'),
      'progress_message' => $this->t('Processed @current out of @total. Estimated: @estimate'),
      'error_message' => $this->t('The generation progress has encountered an error.'),
    ];

    batch_set($batch);

    $form_state->setRedirect('cecc_api.settings');
  }

  /**
   * Inventory refresh batch processing.
   *
   * @param array $context
   *   The batch context.
   */
  public static function batchProcessInventory(array &$context) {
    $batchSize = 10;
    /** @var \Drupal\cecc_api\Service\Stock $stockApi */
    $stockApi = \Drupal::service('cecc_api.stock');

    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = \Drupal::service('entity_type.manager')
        ->getStorage('commerce_product_variation')
        ->getQuery()
        ->count()
        ->execute();
    }

    $start = $context['sandbox']['progress'];
    $max = $context['sandbox']['progress'] + $batchSize;

    if ($max > $context['sandbox']['max']) {
      $max = $context['sandbox']['max'];
    }

    $productVariationIds = \Drupal::service('entity_type.manager')
      ->getStorage('commerce_product_variation')
      ->getQuery()
      ->range($context['sandbox']['progress'], $batchSize)
      ->execute();

    foreach ($productVariationIds as $productVariationId) {
      $productVariation = ProductVariation::load($productVariationId);

      $stockApi->refreshInventory($productVariation);

      $context['sandbox']['progress']++;
      sleep(15);
    }

    $context['message'] = t('Processed @starting through @max of @total.', [
      '@starting' => $start,
      '@max' => $max,
      '@total' => $context['sandbox']['max'],
    ]);

    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * Batch process completion method.
   *
   * @param bool $success
   *   Boolean value that specifies batch success.
   * @param array $results
   *   Array that contains results from batch context.
   * @param array $operations
   *   Array that contains batch operations.
   */
  public static function finishedInventoryRefresh(bool $success, array $results, array $operations) {
    $status = Messenger::TYPE_STATUS;

    if ($success) {
      $message = t('All stock refreshed.');

      \Drupal::logger('cecc_api')->info($message);
    }
    else {
      $message = t('Failed to refresh all stock. Please notify web support.');

      $status = Messenger::TYPE_ERROR;
      \Drupal::logger('cecc_api')->error($message);
    }

    \Drupal::messenger()->addMessage($message, $status);

  }

  /**
   * {@inheritDoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('cecc_api.settings');
  }

  /**
   * {@inheritDoc}
   */
  public function getConfirmText() {
    return $this->t('Refresh All Stock');
  }

  /**
   * {@inheritDoc}
   */
  public function getDescription() {
    $message = $this->t('You are retrieving the current stock for all products. Do you wish to continue?');

    return $message;
  }

}
