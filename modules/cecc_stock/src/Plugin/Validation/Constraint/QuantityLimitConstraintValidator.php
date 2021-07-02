<?php

namespace Drupal\cecc_stock\Plugin\Validation\Constraint;

use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_store\SelectStoreTrait;
use Drupal\Core\Field\FieldItemListInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validator for the Publication order limit constraint.
 */
class QuantityLimitConstraintValidator extends ConstraintValidator {

  use SelectStoreTrait;

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

    /** @var Drupal\commerce_order\Entity\OrderItemInterface $orderItem */
    $orderItem = $value->getEntity();

    $purchasedEntity = $orderItem->getPurchasedEntity();

    if (!$purchasedEntity instanceof PurchasableEntityInterface) {
      // An invalid reference will be handled by the ValidReference constraint.
      return;
    }

    $orderLimit = $purchasedEntity->get('field_maximum_order_amount')->value;

    if ($orderLimit > 0) {
      if ($quantity > $orderLimit) {
        $this->context->buildViolation($constraint->getOverLimitMessage(), [
          '%label' => $purchasedEntity->label(),
          '%limit' => $orderLimit,
        ])->addViolation();
      }

    }

  }
}
