<?php

namespace Drupal\cecc_stock\Plugin\Validation\Constraint;

use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_store\SelectStoreTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\cecc_stock\Service\StockValidation;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validator for the Publication order limit constraint.
 */
class StockLevelConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  use SelectStoreTrait;

  /**
   * Stock validation service.
   *
   * @var \Drupal\cecc_stock\Service\StockValidation
   */
  public $stockValidation;

  public function __construct(StockValidation $stockValidation) {
    $this->stockValidation = $stockValidation;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cecc_stock.stock_validation')
    );
  }

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

    $stockStatus = $this->stockValidation->checkStockLevel($purchasedEntity->id(), $quantity);

    switch ($stockStatus) {
      case StockValidation::ABOVE_STOCK_LEVEL:
        $this->context->buildViolation($constraint->getOverLimitMessage(), [
          '%label' => $purchasedEntity->label(),
          '%quantity' => $quantity,
        ])->addViolation();
        break;

      case StockValidation::ORDER_ABOVE_STOCK_LEVEL:
        $this->context->buildViolation($constraint->getOverLimitMessage(), [
          '%label' => $purchasedEntity->label(),
          '%quantity' => $quantity,
        ])->addViolation();
        break;
    }
  }

}
