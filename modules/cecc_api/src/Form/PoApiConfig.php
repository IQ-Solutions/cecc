<?php

namespace Drupal\cecc_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Manages base Publication Ordering API config.
 */
class PoApiConfig extends ConfigFormBase {

  /**
   * {@inheritDoc}
   */
  protected function getEditableConfigNames() {
    return [
      'cecc_api.settings',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return "cecc_api_settings";
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('cecc_api.settings');

    $form['enable_api'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Warehouse API'),
      '#description' => $this->t('Enables or disables the Warehouse API'),
      '#default_value' => $config->get('enable_api') ?: 1,
    ];

    $form['agency'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Service ID'),
      '#description' => $this->t('This is usually the agency acronym.'),
      '#default_value' => $config->get('agency'),
    ];

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#description' => $this->t('The service API key.'),
      '#default_value' => $config->get('api_key'),
    ];

    $form['base_api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Base API Url'),
      '#description' => $this->t('The base API URL'),
      '#default_value' => $config->get('base_api_url') ?: "https://order-apis.azurewebsites.net",
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('cecc_api.settings');
    $config
      ->set('enable_api', $form_state->getValue('enable_api'))
      ->set('agency', $form_state->getValue('agency'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('base_api_url', $form_state->getValue('base_api_url'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
