<?php

namespace Drupal\cecc_shipping\Service;

use Drupal\commerce_price\Price;
use Drupal\commerce_price\Rounder;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ShipmentWeightCalculator.
 */
class ShipmentWeightCalculator implements ContainerInjectionInterface {

  /**
   * Commerce price rounder.
   *
   * @var \Drupal\commerce_price\Rounder
   */
  protected $priceRounder;

  /**
   * Shipment Weight calculator constructor.
   *
   * @param \Drupal\commerce_price\Rounder $rounder
   *   The price rounder service.
   */
  public function __construct(Rounder $rounder) {
    $this->priceRounder = $rounder;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_price.rounder')
    );
  }

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
    $price = $weightPrice->multiply($weight)
      ->add($basePrice);

    return $this->priceRounder->round($price);
  }

}
