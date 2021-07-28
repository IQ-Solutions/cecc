<?php

namespace Drupal\cecc_cart\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Manages base CECC API config.
 */
class CeccCartConfigForm extends ConfigFormBase {

  /**
   * {@inheritDoc}
   */
  protected function getEditableConfigNames() {
    return [
      'cecc_cart.settings',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return "cecc_cart_settings";
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('cecc_cart.settings');

    $form['use_ajax'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ajax Cart'),
      '#description' => $this->t('Enables the ajax cart functionality'),
      '#default_value' => !empty($config->get('use_ajax')) ?
      $config->get('use_ajax') : FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('cecc_cart.settings')
      ->set('use_ajax', $form_state->getValue('use_ajax'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
