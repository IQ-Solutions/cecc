<?php

namespace Drupal\cecc_checkout\Plugin\Commerce\CheckoutFlow;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowWithPanesBase;

/**
 * Provides the default multistep checkout flow.
 *
 * @CommerceCheckoutFlow(
 *   id = "cecc_multistep_default",
 *   label = "CECC Multistep",
 * )
 */
class MultistepCecc extends CheckoutFlowWithPanesBase {

  /**
   * {@inheritdoc}
   */
  public function getSteps() {
    // Note that previous_label and next_label are not the labels
    // shown on the step itself. Instead, they are the labels shown
    // when going back to the step, or proceeding to the step.
    return [
      'order_notes' => [
        'label' => $this->t('Order Notes'),
        'previous_label' => $this->t('Back to cart'),
        'has_sidebar' => TRUE,
      ],
      'login' => [
        'label' => $this->t('Login'),
        'previous_label' => $this->t('Go Back'),
        'has_sidebar' => FALSE,
      ],
      'order_information' => [
        'label' => $this->t('Order Information'),
        'has_sidebar' => TRUE,
        'previous_label' => $this->t('Go Back'),
      ],
      'payment_information' => [
        'label' => $this->t('Payment Information'),
        'has_sidebar' => TRUE,
        'previous_label' => $this->t('Go Back'),
      ],
      'review' => [
        'label' => $this->t('Review'),
        'next_label' => $this->t('Continue to Review'),
        'previous_label' => $this->t('Go Back'),
        'has_sidebar' => TRUE,
      ],
    ] + parent::getSteps();
  }

}
