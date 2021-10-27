<?php

namespace Drupal\cecc_shipping_account\PluginForm;

use Drupal\commerce_payment\Exception\DeclineException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentMethodFormBase;
use Drupal\Core\Form\FormStateInterface;

class ShippingAccountAddForm extends PaymentMethodFormBase {

  /**
   * {@inheritdoc}
   */
  public function getErrorElement(array $form, FormStateInterface $form_state) {
    return $form['payment_details'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $this->entity;

    $form['payment_details'] = [
      '#parents' => array_merge($form['#parents'], ['payment_details']),
      '#type' => 'container',
      '#payment_method_type' => $payment_method->bundle(),
    ];

    $form['payment_details']['cecc_shipping_vendor'] = [
      '#type' => 'select',
      '#title' => $payment_method->cecc_shipping_vendor->getFieldDefinition()->getLabel(),
      '#options' => $payment_method->cecc_shipping_vendor->getSetting('allowed_values'),
      '#empty_option' => '- Select a value -',
      '#required' => TRUE,
    ];

    $form['payment_details']['cecc_shipping_account'] = [
      '#type' => 'textfield',
      '#title' => $payment_method->cecc_shipping_account->getFieldDefinition()->getLabel(),
      '#attributes' => ['autocomplete' => 'off'],
      '#required' => TRUE,
      '#maxlength' => 60,
      '#size' => 30,
    ];

    $form['reusable'] = [
      '#type' => 'value',
      '#value' => $payment_method->isReusable(),
    ];

    if (!empty($form['#allow_reusable'])) {
      if ($form['#always_save']) {
        $form['reusable']['#value'] = TRUE;
      }
      else {
        $form['reusable'] = [
          '#type' => 'checkbox',
          '#title' => t('Save this payment method for later use'),
          '#default_value' => FALSE,
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    $this->validateShippingAccountForm($form['payment_details'], $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $paymentMethod */
    $paymentMethod = $this->entity;

    $this->submitShippingAccountForm($form['payment_details'], $form_state);

    $values = $form_state->getValue($form['#parents']);
    $paymentMethod->setReusable(!empty($values['reusable']));

    /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $this->plugin;
    // The payment method form is customer facing. For security reasons
    // the returned errors need to be more generic.
    try {
      $payment_gateway_plugin->createPaymentMethod($paymentMethod, $values['payment_details']);
    }
    catch (DeclineException $e) {
      $this->logger->warning($e->getMessage());
      throw new DeclineException('We encountered an error processing your payment method. Please verify your details and try again.');
    }
    catch (PaymentGatewayException $e) {
      $this->logger->error($e->getMessage());
      throw new PaymentGatewayException('We encountered an unexpected error processing your payment method. Please try again later.');
    }
  }

  /**
   * Handles the submission of the credit card form.
   *
   * @param array $element
   *   The credit card form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   */
  protected function submitShippingAccountForm(array $element, FormStateInterface $form_state) {
    $values = $form_state->getValue($element['#parents']);
    $this->entity->cecc_shipping_vendor = $values['cecc_shipping_vendor'];
    $this->entity->cecc_shipping_account = $values['cecc_shipping_account'];
  }

  /**
   * Validates the credit card form.
   *
   * @param array $element
   *   The credit card form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   */
  protected function validateShippingAccountForm(array &$element, FormStateInterface $form_state) {
    $values = $form_state->getValue($element['#parents']);

    if (empty($values['cecc_shipping_vendor'])) {
      $form_state->setError($element['cecc_shipping_vendor'], $this->t('You must select a shipping vendor'));
    }

    if (empty($values['cecc_shipping_account'])) {
      $form_state->setError($element['cecc_shipping_account'], $this->t('You must enter a shipping account'));
    }
  }

}
