<?php

namespace Drupal\cecc_stock\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds customer questions to checkout summary.
 *
 * @CommerceCheckoutPane(
 *   id = "cecc_overlimit",
 *   label = @Translation("Over Limit Order Request"),
 *   default_step = "order_information",
 *   wrapper_element = "fieldset",
 * )
 */
class OverLimit extends CheckoutPaneBase {

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow = NULL) {
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
      'pane_title' => 'Over Limit Order Request',
      'message' => [
        'value' => '<p>Requests for quantities above the limit are considered on a case-by-case basis. Please call the NINDS toll-free number <a href="tel:8003529424">800-352-9424</a> between 8:30 a.m. and 5:00 p.m. Eastern time, Monday through Friday, to place your order and explain how you plan to use our materials.</p><p>You have requested a quantity of product(s) NDS-169 greater than the limit we allow. You can lower your requested quantity OR We may be able to ship the full amount you have requested, but need more information.</p>',
        'format' => 'basic_html',
      ],
      'form_message' => [
        'value' => '<p><strong>Please complete this form to request an over the limit quantity</strong></p><p>Please provide the list of publications, quantities, how they will be used, and the expected audience. We will makes every attempt to ship the amount you requested, however you may receive a smaller amount based on supply.</p>',
        'format' => 'basic_html',
      ],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['pane_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pane Title'),
      '#description' => $this->t('The title of the pane.'),
      '#default_value' => $this->configuration['pane_title'],
      '#required' => TRUE,
    ];

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

    $form['form_message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Text area before the form'),
      '#description' => $this->t('Over limit message text display while editing'),
      '#default_value' => $this->configuration['form_message']['value'],
      '#format' => $this->configuration['form_message']['format'],
      '#element_validate' => ['token_element_validate'],
      '#token_types' => ['commerce_order'],
      '#required' => TRUE,
    ];

    $form['after_form_message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('After form text area'),
      '#description' => $this->t('Displays after the form and on the summary page.'),
      '#default_value' => $this->configuration['after_form_message']['value'],
      '#format' => $this->configuration['after_form_message']['format'],
      '#element_validate' => ['token_element_validate'],
      '#token_types' => ['commerce_order'],
      '#required' => FALSE,
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
      $this->configuration['pane_title'] = $values['pane_title'];
      $this->configuration['message'] = $values['message'];
      $this->configuration['form_message'] = $values['form_message'];
      $this->configuration['after_form_message'] = $values['after_form_message'];
    }
  }

  /**
   * {@inheritDoc}
   */
  public function isVisible() {
    $orderItems = $this->order->getItems();

    foreach ($orderItems as $orderItem) {
      $quantity = $orderItem->getQuantity();
      $purchasedEntity = $orderItem->getPurchasedEntity();
      $overLimitValue = $purchasedEntity->get('field_cecc_order_limit')->value;

      if (!empty($overLimitValue) && $quantity > $overLimitValue) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $message = $this->token->replace($this->configuration['message']['value'], [
      'commerce_order' => $this->order,
    ]);
    $formMessage = $this->token->replace($this->configuration['form_message']['value'], [
      'commerce_order' => $this->order,
    ]);
    $afterFormMessage = $this->token->replace($this->configuration['after_form_message']['value'], [
      'commerce_order' => $this->order,
    ]);

    $pane_form['#title'] = $this->configuration['pane_title'];

    $pane_form['message'] = [
      '#type' => 'markup',
      '#markup' => $message,
    ];

    $pane_form['form_message'] = [
      '#type' => 'markup',
      '#markup' => $formMessage,
    ];

    $pane_form['field_event_name'] = [
      '#type' => 'textfield',
      '#title' => $this->order->get('field_event_name')->getFieldDefinition()->getLabel(),
      '#default_value' => $this->order->get('field_event_name')->isEmpty() ?
      NULL : $this->order->get('field_event_name')->value,
      '#required' => TRUE,
      '#maxlength' => 200,
    ];

    $pane_form['field_event_location'] = [
      '#type' => 'textfield',
      '#title' => $this->order->get('field_event_location')->getFieldDefinition()->getLabel(),
      '#default_value' => $this->order->get('field_event_location')->isEmpty() ?
      NULL : $this->order->get('field_event_location')->value,
      '#required' => TRUE,
      '#maxlength' => 200,
    ];

    $pane_form['field_cecc_over_limit_desc'] = [
      '#type' => 'textarea',
      '#title' => $this->order->get('field_cecc_over_limit_desc')->getFieldDefinition()->getLabel(),
      '#default_value' => $this->order->get('field_cecc_over_limit_desc')->isEmpty() ?
      NULL : $this->order->get('field_cecc_over_limit_desc')->value,
      '#maxlength' => 1000,
      '#required' => TRUE,
    ];

    $pane_form['after_form_message'] = [
      '#type' => 'markup',
      '#markup' => $afterFormMessage,
    ];

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $value = $form_state->getValue($pane_form['#parents']);
    $this->order->set('field_event_name', $value['field_event_name']);
    $this->order->set('field_event_location', $value['field_event_location']);
    $this->order->set('field_cecc_over_limit_desc', $value['field_cecc_over_limit_desc']);
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    $build = [];

    $message = $this->token->replace($this->configuration['message']['value'], [
      'commerce_order' => $this->order,
    ]);
    $afterFormMessage = $this->token->replace($this->configuration['after_form_message']['value'], [
      'commerce_order' => $this->order,
    ]);

    if (!$this->order->get('field_cecc_over_limit_desc')->isEmpty()) {
      $build = [
        'summary_display' => [
          '#type' => 'container',
          '#title' => $this->t('Over Limit Order Request'),
        ],
      ];
      $build['summary_display']['message'] = [
        '#type' => 'markup',
        '#markup' => $message,
      ];

      $build['summary_display']['field_event_name'] = [
        '#type' => 'item',
        '#title' => 'Event Name',
        '#markup' => '<p>' . $this->order->get('field_event_name')->value . '</p>',
      ];

      $build['summary_display']['field_event_location'] = [
        '#type' => 'item',
        '#title' => 'Event Location (State)',
        '#markup' => '<p>' . $this->order->get('field_event_location')->value . '</p>',
      ];

      $build['summary_display']['field_cecc_over_limit_desc'] = [
        '#type' => 'item',
        '#title' => $this->order->get('field_cecc_over_limit_desc')->getFieldDefinition()->getLabel(),
        '#markup' => '<p>' . $this->order->get('field_cecc_over_limit_desc')->value . '</p>',
      ];

      $build['summary_display']['after_form_message'] = [
        '#type' => 'markup',
        '#markup' => $afterFormMessage,
      ];
    }

    return $build;
  }

}
