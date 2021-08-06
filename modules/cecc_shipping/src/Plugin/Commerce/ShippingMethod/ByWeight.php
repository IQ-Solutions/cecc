<?php

namespace Drupal\cecc_shipping\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodBase;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\commerce_shipping\ShippingService;
use Drupal\Core\Form\FormStateInterface;
use Drupal\state_machine\WorkflowManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the ByWeight shipping method.
 *
 * @CommerceShippingMethod(
 *   id = "by_weight",
 *   label = @Translation("By weight"),
 * )
 */
class ByWeight extends ShippingMethodBase {

  /**
   * Shipping by weight shipment weight calculator service.
   *
   * @var \Drupal\commerce_shipping_by_weight\ShipmentWeightCalculator
   */
  protected $shipmentWeightCalculator;

  /**
   * Constructs a new ByWeightShippingMethod object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_shipping\PackageTypeManagerInterface $package_type_manager
   *   The package type manager.
   * @param \Drupal\state_machine\WorkflowManagerInterface $workflow_manager
   *   The workflow manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    PackageTypeManagerInterface $package_type_manager,
    WorkflowManagerInterface $workflow_manager) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $package_type_manager,
      $workflow_manager);
    $this->services['default'] = new ShippingService('default', $this->configuration['rate_label']);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.commerce_package_type'),
      $container->get('plugin.manager.workflow')
    );
    $plugin->shipmentWeightCalculator = $container->get('cecc_shipping.shipment_weight_calculator');
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'rate_label' => '',
      'rate_description' => '',
      'base_price' => NULL,
      'weight_price' => NULL,
      'services' => ['default'],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['rate_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Rate label'),
      '#description' => $this->t('Shown to customers during checkout.'),
      '#default_value' => $this->configuration['rate_label'],
      '#required' => TRUE,
    ];
    $form['rate_description'] = [
      '#type' => 'textfield',
      '#title' => t('Rate description'),
      '#description' => t('Provides additional details about the rate to the customer.'),
      '#default_value' => $this->configuration['rate_description'],
    ];

    $form['base_price'] = [
      '#type' => 'commerce_price',
      '#title' => t('Base price'),
      '#default_value' => $this->getConfigPrice('base_price'),
      '#required' => TRUE,
    ];

    $form['weight_price'] = [
      '#type' => 'commerce_price',
      '#title' => t('Price per lb'),
      '#default_value' => $this->getConfigPrice('weight_price'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['rate_label'] = $values['rate_label'];
      $this->configuration['base_price'] = $values['base_price'];
      $this->configuration['weight_price'] = $values['weight_price'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment) {
    $base_price = $this->getConfigPrice('base_price', TRUE);
    $weight_price = $this->getConfigPrice('weight_price', TRUE);

    $price = $this->getShipmentWeightCalculator()->calculate($shipment, $base_price, $weight_price);

    $rates = [];
    $rates[] = new ShippingRate([
      'shipping_method_id' => $this->parentEntity->id(),
      'service' => $this->services['default'],
      'amount' => $price,
      'description' => $this->configuration['rate_description'],
    ]);

    return $rates;
  }

  /**
   * Gets a price from configuration.
   *
   * @param string $key
   *   The config key.
   * @param bool $price
   *   Return as a price object. Optional, defaults to FALSE.
   *
   * @return float|\Drupal\commerce_price\Price|null
   *   The price either as a numeric value or Price object. NULL if unable to
   *   load from configuration.
   */
  protected function getConfigPrice($key, $price = FALSE) {
    $amount = $this->configuration[$key];
    if (isset($amount) && !isset($amount['number'], $amount['currency_code'])) {
      $amount = NULL;
    }

    if ($price) {
      $amount = new Price($amount['number'], $amount['currency_code']);
    }

    return $amount;
  }

  /**
   * Gets the shipment weight calculator.
   *
   * @return \Drupal\commerce_shipping_by_weight\ShipmentWeightCalculator
   *   The shipment weight calculator.
   */
  protected function getShipmentWeightCalculator() {
    return $this->shipmentWeightCalculator;
  }

}
