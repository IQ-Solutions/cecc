<?php

namespace Drupal\cecc_checkout\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the payment information pane.
 *
 * Disabling this pane will automatically disable the payment process pane,
 * since they are always used together. Developers subclassing this pane
 * should use hook_commerce_checkout_pane_info_alter(array &$panes) to
 * point $panes['payment_information']['class'] to the new child class.
 *
 * Depending on the checkout flow settings the customer might opt-in to storing
 * the payment method. Not opting in only makes the payment methd non-reusable.
 * It's up to the payment gateway plugin to take care of actually storing or
 * not storing the remote or local payment method.
 *
 * @CommerceCheckoutPane(
 *   id = "cecc_billing_notification",
 *   label = @Translation("Billing Notification"),
 *   default_step = "payment_information",
 *   wrapper_element = "fieldset",
 * )
 */
class BillingNotification extends CheckoutPaneBase implements CheckoutPaneInterface {

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?CheckoutFlowInterface $checkout_flow = NULL) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $checkout_flow,
      $container->get('entity_type.manager')
    );
    $instance->setToken($container->get('token'));
    return $instance;
  }

  /**
   * Sets the token service.
   *
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function setToken(Token $token) {
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'message' => [
        'value' => '<p>You have selected Free Shipping. Please disregard the billing address section. You will not be billed.</p>',
        'format' => 'basic_html',
      ],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Top text area'),
      '#description' => $this->t('Text area after the header. Display while editing and on the summary page.'),
      '#default_value' => $this->configuration['message']['value'],
      '#format' => $this->configuration['message']['format'],
      '#element_validate' => ['token_element_validate'],
      '#token_types' => ['commerce_order'],
      '#required' => TRUE,
    ];

    $form['token_help'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['commerce_order'],
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
      $this->configuration['message'] = $values['message'];
    }
  }

  /**
   * {@inheritDoc}
   */
  public function isVisible() {
    $total_price = $this->order->getTotalPrice();
    return $total_price !== NULL && $total_price->isZero();
  }

  /**
   * {@inheritDoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $message = $this->token->replace($this->configuration['message']['value'], [
      'commerce_order' => $this->order,
    ]);

    $pane_form['message'] = [
      '#type' => 'markup',
      '#markup' => $message,
      '#attributes' => [
        'class' => [
          'usa-alert',
          'usa-alert-info',
        ],
      ],
    ];

    return $pane_form;
  }

}
