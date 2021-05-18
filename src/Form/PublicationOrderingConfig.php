<?php

namespace Drupal\publication_ordering\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Manages base Publication Ordering API config.
 */
class PublicationOrderingConfig extends ConfigFormBase {

  /**
   * {@inheritDoc}
   */
  protected function getEditableConfigNames() {
    return [
      'publication_ordering.settings',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return "publication_ordering_settings";
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('publication_ordering.settings');

    $form['quantity_update_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Quantity Update Type'),
      '#description' => $this->t('Changes how item quantities are added to the cart.'),
      '#options' => [
        'normal' => $this->t('Normal - Quantity value always starts at one. The entered value is added to the cart value.'),
        'cart' => $this->t('Cart - Quantity value reflects the cart value. Changing the quantity value changes the cart value.'),
      ],
      '#default_value' => !empty($config->get('quantity_update_type')) ?
      $config->get('quantity_update_type') : 'normal',
    ];

    $form['email_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Email settings'),
    ];

    $form['email_settings']['email_from_name'] = [
      '#name' => 'email_from_name',
      '#type' => 'textfield',
      '#title' => $this->t('Email From Name'),
      '#description' => $this->t('The name shown in the from field when the order receipt is sent.'),
      '#default_value' => $config->get('email_from_name'),
    ];

    $form['email_settings']['email_from'] = [
      '#name' => 'email_from',
      '#type' => 'textfield',
      '#title' => $this->t('Email From'),
      '#description' => $this->t('The email address the reciept was sent from.'),
      '#default_value' => $config->get('email_from'),
    ];

    $form['email_settings']['email_subject'] = [
      '#name' => 'email_subject',
      '#type' => 'textfield',
      '#title' => $this->t('Email Subject'),
      '#description' => $this->t('The email subject. Use <em>@number</em> for the order number place holder'),
      '#default_value' => $config->get('email_subject'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('publication_ordering.settings')
      ->set('quantity_update_type', $form_state->getValue('quantity_update_type'))
      ->set('email_from', $form_state->getValue('email_from'))
      ->set('email_from_name', $form_state->getValue('email_from_name'))
      ->set('email_subject', $form_state->getValue('email_subject'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
