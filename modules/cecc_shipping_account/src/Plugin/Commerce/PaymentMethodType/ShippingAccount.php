<?php

namespace Drupal\cecc_shipping_account\Plugin\Commerce\PaymentMethodType;

use Drupal\entity\BundleFieldDefinition;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\PaymentMethodTypeBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides the PayPal payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "shipping_account",
 *   label = @Translation("Shipping account"),
 * )
 */
class ShippingAccount extends PaymentMethodTypeBase {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method) {
    $args = [
      ':shipping_vendor' => $payment_method->cecc_shipping_vendor->value,
      ':shipping_account' => $payment_method->cecc_shipping_account->value,
    ];
    return $this->t(':shipping_vendor account (:shipping_account)', $args);
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    $fields['cecc_shipping_vendor'] = BundleFieldDefinition::create('list_string')
      ->setLabel($this->t('Shipping Vendor'))
      ->setDescription($this->t('The shipping vendor the account belongs to.'))
      ->setSettings([
        'allowed_values' => [
          'UPS' => 'UPS',
          'FEDEX' => 'FEDEX',
          'DHL' => 'DHL',
          'USPS' => 'USPS',
        ],
      ])
      ->setRequired(TRUE)
      ->setDefaultValue('FEDEX')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
      ]);

    $fields['cecc_shipping_account'] = BundleFieldDefinition::create('string')
      ->setLabel($this->t('Shipping Account'))
      ->setDescription($this->t('The shipping account number.'))
      ->setRequired(TRUE);

    return $fields;
  }

}
