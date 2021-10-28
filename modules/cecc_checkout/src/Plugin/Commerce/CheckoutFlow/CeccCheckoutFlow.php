<?php

namespace Drupal\cecc_checkout\Plugin\Commerce\CheckoutFlow;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowWithPanesBase;

/**
 * CECC Checkout flow class.
 *
 * @CommerceCheckoutFlow(
 *   id = "cecc_checkout_flow",
 *   label = @Translation("CECC Checkout Flow"),
 * )
 */
class CeccCheckoutFlow extends CheckoutFlowWithPanesBase {

  /**
   * {@inheritDoc}
   */
  public function getSteps() {
    return [
      'login' => [
        'label' => $this->t('Login'),
        'previous_label' => $this->t('Go Back'),
        'has_sidebar' => FALSE,
      ],
      'shipping_information' => [
        'label' => $this->t('Shipping Information'),
        'has_sidebar' => TRUE,
        'previous_label' => $this->t('Go Back'),
        'next_label' => $this->t('Continue'),
      ],
      'misc_information' => [
        'label' => $this->t('Additional Information'),
        'has_sidebar' => TRUE,
        'previous_label' => $this->t('Go Back'),
        'next_label' => $this->t('Continue'),
      ],
      'payment_information' => [
        'label' => $this->t('Payment Information'),
        'has_sidebar' => TRUE,
        'next_label' => $this->t('Continue'),
        'previous_label' => $this->t('Go Back'),
      ],
      'review' => [
        'label' => $this->t('Review'),
        'next_label' => $this->t('Continue'),
        'previous_label' => $this->t('Go Back'),
        'has_sidebar' => TRUE,
      ],
    ] + parent::getSteps();
  }

}
