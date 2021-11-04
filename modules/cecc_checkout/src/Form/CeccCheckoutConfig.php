<?php

namespace Drupal\cecc_checkout\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Manages base CECC API config.
 */
class CeccCheckoutConfig extends ConfigFormBase {

  /**
   * {@inheritDoc}
   */
  protected function getEditableConfigNames() {
    return [
      'cecc_checkout.settings',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return "cecc_checkout_settings";
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('cecc_checkout.settings');

    $form['checkout_flow_buttons'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Checkout Buttons'),
    ];

    $form['checkout_flow_buttons']['back_to_cart'] = [
      '#name' => 'back_to_cart',
      '#type' => 'checkbox',
      '#title' => $this->t('Show "Back to Cart" button'),
      '#description' => $this->t('Show or hide the back to cart button'),
      '#default_value' => !empty($config->get('back_to_cart')) ?
      $config->get('back_to_cart') : 0,
    ];

    $form['checkout_flow_labels'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Checkout Flow Labels'),
    ];

    $form['checkout_flow_labels']['log_in_step'] = [
      '#type' => 'details',
      '#title' => $this->t('Log In Step'),
    ];

    $form['checkout_flow_labels']['log_in_step']['log_in_label'] = [
      '#name' => 'log_in_label',
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('This is the title on the log in page.'),
      '#default_value' => !empty($config->get('log_in_label')) ?
      $config->get('log_in_label') : 'Log In',
      '#required' => TRUE,
    ];

    $form['checkout_flow_labels']['log_in_step']['log_in_previous'] = [
      '#name' => 'log_in_previous',
      '#type' => 'textfield',
      '#title' => $this->t('Previous Button'),
      '#description' => $this->t('The previous button text.'),
      '#default_value' => !empty($config->get('log_in_previous')) ?
      $config->get('log_in_previous') : 'Go Back',
    ];

    $form['checkout_flow_labels']['shipping_information'] = [
      '#type' => 'details',
      '#title' => $this->t('Shipping Information Step'),
    ];

    $form['checkout_flow_labels']['shipping_information']['shipping_information_label'] = [
      '#name' => 'shipping_information_label',
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('This is the title on the shipping information step.'),
      '#default_value' => !empty($config->get('shipping_information_label')) ?
      $config->get('shipping_information_label') : 'Shipping Information',
      '#required' => TRUE,
    ];

    $form['checkout_flow_labels']['shipping_information']['shipping_information_next'] = [
      '#name' => 'shipping_information_next',
      '#type' => 'textfield',
      '#title' => $this->t('Next Button'),
      '#description' => $this->t('The next button text.'),
      '#default_value' => !empty($config->get('shipping_information_next')) ?
      $config->get('shipping_information_next') : 'Continue',
      '#required' => TRUE,
    ];

    $form['checkout_flow_labels']['shipping_information']['shipping_information_previous'] = [
      '#name' => 'shipping_information_previous',
      '#type' => 'textfield',
      '#title' => $this->t('Previous Button'),
      '#description' => $this->t('The previous button text.'),
      '#default_value' => !empty($config->get('shipping_information_previous')) ?
      $config->get('shipping_information_previous') : 'Go Back',
    ];

    $form['checkout_flow_labels']['misc_information'] = [
      '#type' => 'details',
      '#title' => $this->t('Additional Information Step'),
    ];

    $form['checkout_flow_labels']['misc_information']['misc_information_label'] = [
      '#name' => 'misc_information_label',
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('This is the title on the additional information step.'),
      '#default_value' => !empty($config->get('misc_information_label')) ?
      $config->get('misc_information_label') : 'Additional Information',
      '#required' => TRUE,
    ];

    $form['checkout_flow_labels']['misc_information']['misc_information_next'] = [
      '#name' => 'misc_information_next',
      '#type' => 'textfield',
      '#title' => $this->t('Next Button'),
      '#description' => $this->t('The next button text.'),
      '#default_value' => !empty($config->get('misc_information_next')) ?
      $config->get('misc_information_next') : 'Continue',
      '#required' => TRUE,
    ];

    $form['checkout_flow_labels']['misc_information']['misc_information_previous'] = [
      '#name' => 'misc_information_previous',
      '#type' => 'textfield',
      '#title' => $this->t('Previous Button'),
      '#description' => $this->t('The previous button text.'),
      '#default_value' => !empty($config->get('misc_information_previous')) ?
      $config->get('misc_information_previous') : 'Go Back',
    ];

    $form['checkout_flow_labels']['payment_information'] = [
      '#type' => 'details',
      '#title' => $this->t('Payment Information Step'),
    ];

    $form['checkout_flow_labels']['payment_information']['payment_information_label'] = [
      '#name' => 'payment_information_label',
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('This is the title on the payment information step.'),
      '#default_value' => !empty($config->get('payment_information_label')) ?
      $config->get('payment_information_label') : 'Payment Information',
      '#required' => TRUE,
    ];

    $form['checkout_flow_labels']['payment_information']['payment_information_next'] = [
      '#name' => 'payment_information_next',
      '#type' => 'textfield',
      '#title' => $this->t('Next Button'),
      '#description' => $this->t('The next button text.'),
      '#default_value' => !empty($config->get('payment_information_next')) ?
      $config->get('payment_information_next') : 'Continue',
      '#required' => TRUE,
    ];

    $form['checkout_flow_labels']['payment_information']['payment_information_previous'] = [
      '#name' => 'payment_information_previous',
      '#type' => 'textfield',
      '#title' => $this->t('Previous Button'),
      '#description' => $this->t('The previous button text.'),
      '#default_value' => !empty($config->get('payment_information_previous')) ?
      $config->get('payment_information_previous') : 'Go Back',
    ];

    $form['checkout_flow_labels']['review'] = [
      '#type' => 'details',
      '#title' => $this->t('Review Step'),
    ];

    $form['checkout_flow_labels']['review']['review_label'] = [
      '#name' => 'review_label',
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('This is the title on the payment information step.'),
      '#default_value' => !empty($config->get('review_label')) ?
      $config->get('review_label') : 'Review',
      '#required' => TRUE,
    ];

    $form['checkout_flow_labels']['review']['review_next'] = [
      '#name' => 'review_next',
      '#type' => 'textfield',
      '#title' => $this->t('Next Button'),
      '#description' => $this->t('The next button text.'),
      '#default_value' => !empty($config->get('review_next')) ?
      $config->get('review_next') : 'Continue',
      '#required' => TRUE,
    ];

    $form['checkout_flow_labels']['review']['review_previous'] = [
      '#name' => 'review_previous',
      '#type' => 'textfield',
      '#title' => $this->t('Previous Button'),
      '#description' => $this->t('The previous button text.'),
      '#default_value' => !empty($config->get('review_previous')) ?
      $config->get('review_previous') : 'Go Back',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('cecc_checkout.settings');
    $config
      ->set('log_in_label', $form_state->getValue('log_in_label'))
      ->set('log_in_previous', $form_state->getValue('log_in_previous'))
      ->set('shipping_information_label', $form_state->getValue('shipping_information_label'))
      ->set('shipping_information_previous', $form_state->getValue('shipping_information_previous'))
      ->set('shipping_information_next', $form_state->getValue('shipping_information_next'))
      ->set('misc_information_label', $form_state->getValue('misc_information_label'))
      ->set('misc_information_previous', $form_state->getValue('misc_information_previous'))
      ->set('misc_information_next', $form_state->getValue('misc_information_next'))
      ->set('payment_information_label', $form_state->getValue('payment_information_label'))
      ->set('payment_information_previous', $form_state->getValue('payment_information_previous'))
      ->set('payment_information_next', $form_state->getValue('payment_information_next'))
      ->set('review_label', $form_state->getValue('review_label'))
      ->set('review_previous', $form_state->getValue('review_previous'))
      ->set('review_next', $form_state->getValue('review_next'))
      ->set('back_to_cart', $form_state->getValue('back_to_cart'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
