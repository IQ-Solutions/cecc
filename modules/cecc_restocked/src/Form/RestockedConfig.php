<?php

namespace Drupal\cecc_restocked\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Manages base CECC API config.
 */
class RestockedConfig extends ConfigFormBase {

  /**
   * {@inheritDoc}
   */
  protected function getEditableConfigNames() {
    return [
      'cecc_restocked.settings',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return "cecc_restocked_settings";
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('cecc_restocked.settings');

    $form['restocked_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Restocked Message'),
      '#description' => $this->t('Message sent when an item is restocked.'),
      '#default_value' => $config->get('text'),
      '#element_validate' => ['token_element_validate'],
      '#token_types' => ['commerce_product'],
      '#required' => TRUE,
      '#rows' => 15,
      '#cols' => 150,
    ];

    $form['token_help'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['commerce_product'],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('cecc_restocked.settings');
    $restockedMessage = $form_state->getValue('restocked_message');
    $config
      ->set('text', $restockedMessage)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
