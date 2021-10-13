<?php

namespace Drupal\cecc_shipping\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\PaymentGatewayBase;

/**
 * Privates a Payment gateway for shipping accounts.
 *
 * @CommercePaymentGateway(
 *   id = "cecc_shipping_account_gateway",
 *   label = @Translation("Shipping Account Gateway"),
 *   display_label = @Translation("Shipping Account"),
 *   modes = {
 *     "n/a" = @Translation("N/A"),
 *   },
 *   forms = {
 *     "add-payment" = "Drupal\cecc_shipping\PluginForm\ShippingAccountAddForm",
 *   },
 *   payment_method_types = {"shipping_account"},
 *   requires_billing_information = FALSE,
 * )
 */
class ShippingAccountGateway extends OnsitePaymentGatewayBase implements ShippingAccountInterface {

  /**
   * {@inheritDoc}
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE) {
    $this->assertPaymentState($payment, ['new']);
    $payment_method = $payment->getPaymentMethod();
    assert($payment_method instanceof PaymentMethodInterface);
    $this->assertPaymentMethod($payment_method);

    $payment->setState('completed');
    $payment->save();

  }

  /**
   * {@inheritDoc}
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details) {
    $required_keys = [
      // The expected keys are payment gateway specific and usually match
      // the PaymentMethodAddForm form elements. They are expected to be valid.
      'shipping_account',
      'shipping_vendor',
    ];

    foreach ($required_keys as $required_key) {
      if (empty($payment_details[$required_key])) {
        throw new \InvalidArgumentException(sprintf('$payment_details must contain the %s key.', $required_key));
      }
    }

    // Add a built in test for testing decline exceptions.
    // Note: Since requires_billing_information is FALSE, the payment method
    // is not guaranteed to have a billing profile. Confirm tha
    // $payment_method->getBillingProfile() is not NULL before trying to use it.
    if ($billing_profile = $payment_method->getBillingProfile()) {
      /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $billing_address */
      $billing_address = $billing_profile->get('address')->first();
      if ($billing_address->getPostalCode() == '53141') {
        throw new HardDeclineException('The payment method was declined');
      }
    }

    $payment_method->shipping_vendor = $payment_details['shipping_vendor'];
    $payment_method->shipping_account = $payment_details['shipping_account'];
    $payment_method->save();
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method) {
    // Delete the remote record here, throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
    // Delete the local entity.
    $payment_method->delete();
  }

}
