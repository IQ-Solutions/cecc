<?php

namespace Drupal\cecc_checkout\Plugin\Commerce\CheckoutFlow;

use Drupal\commerce_checkout\CheckoutPaneManager;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowWithPanesBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * CECC Checkout flow class.
 *
 * @CommerceCheckoutFlow(
 *   id = "cecc_checkout_flow",
 *   label = @Translation("CECC Checkout Flow"),
 * )
 */
class CeccCheckoutFlow extends CheckoutFlowWithPanesBase {

  /**
   * Drupal config service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new CheckoutFlowWithPanesBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pane_id
   *   The plugin_id for the plugin instance.
   * @param mixed $pane_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\commerce_checkout\CheckoutPaneManager $pane_manager
   *   The checkout pane manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(
    array $configuration,
    $pane_id,
    $pane_definition,
    EntityTypeManagerInterface $entity_type_manager,
    EventDispatcherInterface $event_dispatcher,
    RouteMatchInterface $route_match,
    CheckoutPaneManager $pane_manager,
    ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
    parent::__construct($configuration, $pane_id, $pane_definition, $entity_type_manager, $event_dispatcher, $route_match, $pane_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pane_id, $pane_definition) {
    return new static(
      $configuration,
      $pane_id,
      $pane_definition,
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
      $container->get('current_route_match'),
      $container->get('plugin.manager.commerce_checkout_pane'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getSteps() {
    $config = $this->configFactory->get('cecc_checkout.settings');

    return [
      'login' => [
        'label' => $config->get('log_in_label'),
        'previous_label' => $config->get('log_in_previous'),
        'has_sidebar' => FALSE,
      ],
      'shipping_information' => [
        'label' => $config->get('shipping_information_label'),
        'has_sidebar' => TRUE,
        'previous_label' => $config->get('shipping_information_previous'),
        'next_label' => $config->get('shipping_information_next'),
      ],
      'misc_information' => [
        'label' => $config->get('misc_information_label'),
        'has_sidebar' => TRUE,
        'previous_label' => $config->get('misc_information_previous'),
        'next_label' => $config->get('misc_information_next'),
      ],
      'payment_information' => [
        'label' => $config->get('payment_information_label'),
        'has_sidebar' => TRUE,
        'previous_label' => $config->get('payment_information_previous'),
        'next_label' => $config->get('payment_information_next'),
      ],
      'review' => [
        'label' => $config->get('review_label'),
        'has_sidebar' => TRUE,
        'previous_label' => $config->get('review_previous'),
        'next_label' => $config->get('review_next'),
      ],
    ] + parent::getSteps();
  }

}
