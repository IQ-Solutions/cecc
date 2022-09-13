<?php

namespace Drupal\cecc_order\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Manages base Publication Ordering API config.
 */
class CeccOrderForm extends ConfigFormBase {

  /**
   * {@inheritDoc}
   */
  protected function getEditableConfigNames() {
    return [
      'cecc_order.settings',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return "cecc_order_settings";
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('cecc_order.settings');
    $order_types = \Drupal::entityTypeManager()
      ->getStorage('commerce_order_type')->loadMultiple();
    $order_item_types = \Drupal::entityTypeManager()
      ->getStorage('commerce_order_item_type')->loadMultiple();

    $order_type_options = [];
    $order_item_type_options = [];

    foreach ($order_types as $key => $product_type) {
      $type_options[$key] = $product_type->label()." ($key)";
    }

    foreach ($order_item_types as $key => $product_type) {
      $type_options[$key] = $product_type->label()." ($key)";
    }

    $form['commerce_order_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Select the order type'),
      '#description' => $this->t('Choose the order type the CECC module will modify'),
      '#options' => $order_type_options,
      '#default_value' => !empty($config->get('commerce_order_type')) ?
      $config->get('commerce_order_type') : 'cecc_publication',
    ];

    $form['commerce_order_item_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Select the product type'),
      '#description' => $this->t('Choose the order item type the CECC module will modify'),
      '#options' => $order_item_type_options,
      '#default_value' => !empty($config->get('commerce_order_item_type')) ?
      $config->get('commerce_order_item_type') : 'cecc_publication',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('cecc_order.settings')
      ->set('commerce_order_type', $form_state->getValue('commerce_order_type'))
      ->save();
    $this->config('cecc_order.settings')
      ->set('commerce_order_item_type', $form_state->getValue('commerce_order_item_type'))
      ->save();

    $this->messenger()->addStatus('Please clear Drupal cache for these update to take effect.');

    parent::submitForm($form, $form_state);
  }

}
