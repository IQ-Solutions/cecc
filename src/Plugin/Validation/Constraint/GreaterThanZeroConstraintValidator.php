<?php

namespace Drupal\cecc\Plugin\Validation\Constraint;

use Drupal\Core\Field\FieldItemListInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validator for the Publication order limit constraint.
 */
class GreaterThanZeroConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritDoc}
   */
  public function validate($value, Constraint $constraint) {
    if (!assert($value instanceof FieldItemListInterface)) {
      return;
    }

    if ($value->isEmpty()) {
      return;
    }

    $quantity = $value->value;

    if ($quantity <= 0) {
      $this->context->buildViolation($constraint->mustNotBeZero)->addViolation();
    }
  }

}
