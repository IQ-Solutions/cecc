<?php

namespace Drupal\cecc_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Manages base CECC API config.
 */
class CeccApiConfig extends ConfigFormBase {

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

    $form['stock_refresh'] = [
      '#type' => 'details',
      '#title' => $this->t('Stock Refresh Configuration'),
      '#open' => TRUE,
    ];

    $form['stock_refresh']['stock_refresh_type'] = [
      '#name' => 'stock_refresh_type',
      '#type' => 'radios',
      '#title' => $this->t('Stock Refresh Type'),
      '#description' => $this->t('Choose stock refresh type.'),
      '#default_value' => $config->get('stock_refresh_type') ?: 'interval',
      '#options' => [
        'interval' => $this->t('Refresh all stock at specific intervals.'),
        'on_demand' => $this->t('Refresh product stock when criteria met.'),
      ],
    ];

    $form['stock_refresh']['timing'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Stock Refresh Configuration'),
      '#states' => [
        'visible' => [
          ':input[name="stock_refresh_type"]' => ['value' => 'interval'],
        ],
      ],
    ];

    $form['stock_refresh']['timing']['stock_refresh_interval'] = [
      '#name' => 'stock_refresh_interval',
      '#type' => 'select',
      '#title' => $this->t('Stock Refresh Interval'),
      '#description' => $this->t('How often should the stock refresh happen if interval refresh is used.'),
      '#default_value' => $config->get('stock_refresh_interval') ?: '+1 day',
      '#options' => [
        '+12 hours' => $this->t('Every 12 hours'),
        '+8 hours' => $this->t('Every 8 hours'),
        '+6 hours' => $this->t('Every 6 hours'),
        '+1 hour' => $this->t('Hourly'),
        '+1 day' => $this->t('Daily'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="stock_refresh_type"]' => ['value' => 'interval'],
        ],
      ],
    ];

    $form['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Debug API'),
      '#description' => $this->t('Outputs API info to browser or as file.'),
      '#default_value' => $config->get('debug') ?: 0,
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
      ->set('debug', $form_state->getValue('debug'))
      ->set('agency', $form_state->getValue('agency'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('base_api_url', $form_state->getValue('base_api_url'))
      ->set('stock_refresh_type', $form_state->getValue('stock_refresh_type'))
      ->set('stock_refresh_interval', $form_state->getValue('stock_refresh_interval'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
