<?php

namespace Drupal\cecc_publication\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Manages base Publication Ordering API config.
 */
class CeccPublicationForm extends ConfigFormBase {

  /**
   * {@inheritDoc}
   */
  protected function getEditableConfigNames() {
    return [
      'cecc_publication.settings',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return "cecc_publication_settings";
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('cecc_publication.settings');
    $product_types = \Drupal::entityTypeManager()
      ->getStorage('commerce_product_type')->loadMultiple();

    $type_options = [];

    foreach ($product_types as $key => $product_type) {
      $type_options[$key] = $product_type->label()." ($key)";
    }

    $form['commerce_product_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Select the product type'),
      '#description' => $this->t('Choose the product type the CECC module will modify'),
      '#options' => $type_options,
      '#default_value' => !empty($config->get('commerce_product_type')) ?
      $config->get('commerce_product_type') : 'cecc_publication',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('cecc_publication.settings')
      ->set('commerce_product_type', $form_state->getValue('commerce_product_type'))
      ->save();

    $this->messenger()->addStatus('Please clear Drupal cache for these update to appear.');

    parent::submitForm($form, $form_state);
  }

}
