<?php

namespace Drupal\cecc_api\Form;

use Drupal\cecc_api\Service\InventoryApi;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Url;
use Drupal\cecc_api\Service\Stock;
use Drupal\Core\Entity\EntityStorageException;
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
   * Guzzle\Client instance.
   *
   * @var \Drupal\cecc_api\Service\InventoryApi
   */
  protected $inventoryApi;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\cecc_api\Service\Stock $stock
   *   The stock service.
   */
  public function __construct(
    Stock $stock,
    InventoryApi $inventory_api) {

    $this->stock = $stock;
    $this->inventoryApi = $inventory_api;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cecc_api.stock'),
      $container->get('cecc_api.inventory_api')
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

    if ($this->inventoryApi->apiActive) {
      $response = $this->inventoryApi->getAllInventory();

      if (!empty($response)) {
        $operations = [];
        $batchSize = 20;
        $total = count($response);

        foreach (array_chunk($response, $batchSize) as $index => $batchData) {
          $operations[] = [
            '\Drupal\cecc_api\Form\ConfirmProductRestockAllForm::batchProcessInventory',
            [$batchData, $batchSize, $index, $total],
          ];
        }

        $batch = [
          'title' => $this->t('Refresh All Product Inventory'),
          'operations' => $operations,
          'finished' => '\Drupal\cecc_api\Form\ConfirmProductRestockAllForm::finishedInventoryRefresh',
          'init_message' => $this->t('Loading products'),
          'progress_message' => $this->t('Processed @current out of @total. Estimated: @estimate'),
          'error_message' => $this->t('The generation progress has encountered an error.'),
        ];

        batch_set($batch);

      }
      else {
        if (!empty($this->inventoryApi->connectionError)) {
          $this->logger('cecc_api')->warning($this->inventoryApi->connectionError);
        }

        $this->messenger()->addWarning($this->t('Could not retrieve inventory. Please check the error logs for more information.'));
      }
    }

    $form_state->setRedirect('cecc_api.settings');
  }

  /**
   * Inventory refresh batch processing.
   *
   * @param array $context
   *   The batch context.
   */
  public static function batchProcessInventory(array $batchData, int $batchSize, int $index, int $total, array &$context) {

    $storage = \Drupal::entityTypeManager()->getStorage('commerce_product_variation');
    $logger = \Drupal::logger('cecc_api');
    $messenger = \Drupal::messenger();

    foreach ($batchData as $data) {
      $cpvId = $storage->getQuery()
        ->condition('field_cecc_warehouse_item_id', $data['warehouse_item_id'])
        ->execute();

      $productVariation = ProductVariation::load(reset($cpvId));

      if ($productVariation) {
        $productVariation->set('field_cecc_stock', $data['warehouse_stock_on_hand']);

        try {
          $productVariation->save();
          $message = t('Stock for %label has been refreshed to %level', [
            '%label' => $productVariation->getTitle(),
            '%level' => $productVariation->get('field_cecc_stock')->value,
          ]);

          $logger->info($message);
          $messenger->addStatus($message);
        }
        catch (EntityStorageException $error) {
          $logger->error($error->getMessage());
          $messenger->addError(t('%label failed to update. Check the error logs for more information.', [
            '%label' => $productVariation->getTitle(),
          ]));
        }
      }
      else {
        $message = t('Could not update inventory for item with warehouse id: %warehouse_id', [
          '%warehouse_id' => $data['warehouse_item_id'],
        ]);
        $logger->info($message);
        $messenger->addStatus($message);
      }
    }

    $start = $index * $batchSize + 1;
    $max = $start + $batchSize - 1;

    $context['message'] = t('Processed @starting through @max of @total.', [
      '@starting' => $start,
      '@max' => $max,
      '@total' => $total,
    ]);

    $context['finished'] = TRUE;
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
