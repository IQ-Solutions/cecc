<?php

namespace Drupal\cecc_shipping_account\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsUpdatingStoredPaymentMethodsInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;

/**
 * Privates a Payment gateway for shipping accounts.
 *
 * @CommercePaymentGateway(
 *   id = "cecc_shipping_account",
 *   label = @Translation("Shipping Account Gateway"),
 *   display_label = @Translation("Shipping Account"),
 *   modes = {
 *     "n/a" = @Translation("N/A"),
 *   },
 *   forms = {
 *     "add-payment-method" = "Drupal\cecc_shipping_account\PluginForm\ShippingAccountAddForm",
 *     "edit-payment-method" = "Drupal\cecc_shipping_account\PluginForm\ShippingAccountEditForm",
 *   },
 *   payment_method_types = {"shipping_account"},
 *   requires_billing_information = FALSE,
 * )
 */
class ShippingAccount extends OnsitePaymentGatewayBase implements SupportsStoredPaymentMethodsInterface, SupportsUpdatingStoredPaymentMethodsInterface {

  /**
   * {@inheritDoc}
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE) {
    $this->assertPaymentState($payment, ['new']);
    $paymentMethod = $payment->getPaymentMethod();
    $this->assertPaymentMethod($paymentMethod);

    $payment->setState('completed');
    $payment->save();
  }

  /**
   * {@inheritDoc}
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details) {
    $requiredKeys = [
      'cecc_shipping_vendor',
      'cecc_shipping_account',
    ];

    foreach ($requiredKeys as $requiredKey) {
      if (empty($payment_details[$requiredKey])) {
        throw new \InvalidArgumentException(sprintf('$payment_details must contain the %s key.', $requiredKey));
      }
    }

    $payment_method->set('cecc_shipping_vendor', Xss::filter($payment_details['cecc_shipping_vendor']));
    $payment_method->set('cecc_shipping_account', Xss::filter($payment_details['cecc_shipping_account']));
    $payment_method->save();
  }

  /**
   * {@inheritDoc}
   */
  public function updatePaymentMethod(PaymentMethodInterface $payment_method) {

  }

  /**
   * {@inheritDoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method) {
    $payment_method->delete();
  }

}
