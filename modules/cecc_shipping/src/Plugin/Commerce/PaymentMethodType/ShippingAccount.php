<?php

namespace Drupal\cecc_shipping\Plugin\Commerce\PaymentMethodType;

use Drupal\entity\BundleFieldDefinition;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\PaymentMethodTypeBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides the PayPal payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "shipping_account",
 *   label = @Translation("Shipping Account"),
 * )
 */
class ShippingAccount extends PaymentMethodTypeBase {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method) {
    $args = [
      '@shipping_vendor' => $payment_method->shipping_vendor,
      '@shipping_account' => $payment_method->shipping_account,
    ];
    return $this->t('@shipping_vendor Account (@shipping_account)', $args);
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    $fields['shipping_vendor'] = BundleFieldDefinition::create('list_string')
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

    $fields['shipping_account'] = BundleFieldDefinition::create('string')
      ->setLabel($this->t('Shipping Account'))
      ->setDescription($this->t('The shipping account number.'))
      ->setRequired(TRUE);

    return $fields;
  }

}
