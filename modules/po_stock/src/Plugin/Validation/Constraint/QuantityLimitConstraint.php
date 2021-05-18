<?php

namespace Drupal\po_stock\Plugin\Validation\Constraint;

use Drupal\Component\Utility\Xss;
use Symfony\Component\Validator\Constraint;

/**
 * Checks that the order quantity does not exceed ther maximum order amount.
 *
 * @Constraint(
 *   id = "QuantityLimit",
 *   label = @Translation("Maximum Publication Order Amount", context="Validation")
 * )
 */
class QuantityLimitConstraint extends Constraint {

  /**
   * The over order limit message.
   *
   * @var string
   */
  public $quantityOverLimit = 'Need to order more than %limit of %label. Contact the National Oral Health Information Center at 1-866-232-4528 or nidcrinfo@mail.nih.gov.';

  /**
   * Order over quantity limit.
   *
   * @var string
   */
  public $orderQuantityOverLimit = 'You cart already has %orderQuantity for %label. Need to order more than %limit of %label. Contact the National Oral Health Information Center at <a href="tel:+18662324528">1-866-232-4528</a> or <a href="mailto:nidcrinfo@mail.nih.gov">nidcrinfo@mail.nih.gov</a>.';

  public function getOverLimitMessage($type = 'default') {
    $config = \Drupal::config('po_stock.settings');

    if ($type == 'default') {
      return Xss::filterAdmin($config->get('over_limit_text'));
    }
    else {
      return Xss::filterAdmin($config->get('order_over_limit_text'));
    }
  }

}
