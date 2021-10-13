<?php

namespace Drupal\cecc_shipping\PluginForm;

use Drupal\commerce_payment\PluginForm\PaymentGatewayFormBase;
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

    $form['payment_details']['shipping_vendor'] = [
      '#type' => 'select',
      '#title' => $this->t('Month'),
      '#options' => [],
      //'#default_value' => date('m'),
      '#required' => TRUE,
    ];

    $form['payment_details']['shipping_account'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Shipping Account'),
      '#attributes' => ['autocomplete' => 'off'],
      '#required' => TRUE,
      '#maxlength' => 40,
      '#size' => 30,
    ];
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    $order = $payment->getOrder();
    if (!$order) {
      throw new \InvalidArgumentException('Payment entity with no order reference given to PaymentAddForm.');
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue($form['#parents']);
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $paymentMethod */
    $paymentMethod = $this->entity;
    /** @var \Drupal\cecc_shipping\Plugin\Commerce\PaymentGateway\ShippingAccountGateway $paymentGatewayPlugin */
    $paymentGatewayPlugin = $this->plugin;
    $paymentGatewayPlugin->createPaymentMethod($paymentMethod, $values['payment_details']);
  }

}
