<?php

namespace Drupal\cecc\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the order quantity does not exceed ther maximum order amount.
 *
 * @Constraint(
 *   id = "GreaterThanZero",
 *   label = @Translation("Value greater than zero", context="Validation")
 * )
 */
class GreaterThanZeroConstraint extends Constraint {

  /**
   * The over order limit message.
   *
   * @var string
   */
  public $mustNotBeZero = 'Quantity entered must be more than zero.';

}
