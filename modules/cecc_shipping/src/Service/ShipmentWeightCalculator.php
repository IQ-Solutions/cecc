<?php

namespace Drupal\cecc_shipping\Service;

use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShipmentInterface;

/**
 * Class ShipmentWeightCalculator.
 */
class ShipmentWeightCalculator {

  /**
   * Calculate the price of a shipment based on weight.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   * @param \Drupal\commerce_price\Price $basePrice
   *   The base price.
   * @param \Drupal\commerce_price\Price $weightPrice
   *   The price per weight unit.
   *
   * @return \Drupal\commerce_price\Price
   *   The price of the shipment based on weight.
   */
  public function calculate(ShipmentInterface $shipment, Price $basePrice, Price $weightPrice) {
    $weight = $shipment->getWeight()->convert('lb')->getNumber();
    return $weightPrice->multiply($weight)
      ->add($basePrice);
  }

}
