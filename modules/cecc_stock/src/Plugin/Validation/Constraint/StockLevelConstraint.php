<?php

namespace Drupal\cecc_stock\Plugin\Validation\Constraint;

use Drupal\Component\Utility\Xss;
use Symfony\Component\Validator\Constraint;

/**
 * Checks that the order quantity does not exceed ther maximum order amount.
 *
 * @Constraint(
 *   id = "StockLevel",
 *   label = @Translation("Maximum Publication Order Amount", context="Validation")
 * )
 */
class StockLevelConstraint extends Constraint {

  /**
   * The over order limit message.
   *
   * @var string
   */
  public $aboveStockLevel = "Sorry, we do not have enough stock to add %label to your cart.";

  /**
   * Order over quantity limit.
   *
   * @var string
   */
  public $orderAboveStockLevel = 'Sorry, we do not have enough stock to add more of %label to your cart.';

  public function getOverLimitMessage($type = 'default') {
    $config = \Drupal::config('cecc_stock.settings');

    if ($type == 'default') {
      return Xss::filterAdmin($config->get('over_stock_level'));
    }
    else {
      return Xss::filterAdmin($config->get('order_over_stock_level'));
    }
  }

}
