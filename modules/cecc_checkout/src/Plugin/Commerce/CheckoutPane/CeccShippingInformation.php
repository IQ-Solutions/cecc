<?php

namespace Drupal\cecc_checkout\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\BillingInformation;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides customizations to the billing info pane.
 */
class CeccShippingInformation extends BillingInformation {

  /**
   * {@inheritDoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form = parent::buildPaneForm($pane_form, $form_state, $complete_form);

    $pane_form['#title'] = $this->t("Shipping Information");

    return $pane_form;
  }

}