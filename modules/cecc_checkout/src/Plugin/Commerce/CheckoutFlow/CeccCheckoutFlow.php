<?php

namespace Drupal\cecc_checkout\Plugin\Commerce\CheckoutFlow;

use Drupal\commerce_checkout\CheckoutPaneManager;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowWithPanesBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
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
      'payment_information' => [
        'label' => $config->get('payment_information_label'),
        'has_sidebar' => TRUE,
        'previous_label' => $config->get('payment_information_previous'),
        'next_label' => $config->get('payment_information_next'),
      ],
      'misc_information' => [
        'label' => $config->get('misc_information_label'),
        'has_sidebar' => TRUE,
        'previous_label' => $config->get('misc_information_previous'),
        'next_label' => $config->get('misc_information_next'),
      ],
      'review' => [
        'label' => $config->get('review_label'),
        'has_sidebar' => TRUE,
        'previous_label' => $config->get('review_previous'),
        'next_label' => $config->get('review_next'),
      ],
    ] + parent::getSteps();
  }

  /**
   * {@inheritDoc}
   */
  protected function isStepVisible($step_id) {
    if ($step_id == 'payment_information') {
      return parent::isStepVisible($step_id) && !$this->order->getTotalPrice()->isZero();
    }

    return parent::isStepVisible($step_id);
  }

  /**
   * {@inheritDoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('cecc_checkout.settings');
    $steps = $this->getSteps();
    $next_step_id = $this->getNextStepId($form['#step_id']);
    $previous_step_id = $this->getPreviousStepId($form['#step_id']);
    $has_next_step = $next_step_id && isset($steps[$next_step_id]['next_label']);
    $has_previous_step = $previous_step_id && isset($steps[$previous_step_id]['previous_label']);

    $actions = [
      '#type' => 'actions',
      '#access' => $has_next_step && $form['#step_id'] != 'login',
    ];

    if ($has_next_step) {
      $actions['next'] = [
        '#type' => 'submit',
        '#value' => $steps[$next_step_id]['next_label'],
        '#button_type' => 'primary',
        '#submit' => ['::submitForm'],
        '#weight' => 2,
      ];
      if ($has_previous_step) {
        $actions['previous'] = [
          '#type' => 'link',
          '#title' => $steps[$previous_step_id]['previous_label'],
          '#url' => Url::fromRoute('commerce_checkout.form', [
            'commerce_order' => $this->order->id(),
            'step' => $previous_step_id,
          ]),
          '#weight' => 1,
          '#attributes' => [
            'class' => [
              'usa-button',
              'usa-button--outline',
              'link--previous',
            ],
          ],
        ];
      }

      if ($config->get('back_to_cart') == 1) {
        $actions['back_to_cart'] = [
          '#type' => 'link',
          '#title' => $this->t('Back to Cart'),
          '#url' => Url::fromRoute('commerce_cart.page'),
          '#weight' => 0,
          '#attributes' => [
            'class' => [
              'usa-button',
              'usa-button--outline',
            ],
          ],
        ];
      }
    }

    return $actions;
  }

}
