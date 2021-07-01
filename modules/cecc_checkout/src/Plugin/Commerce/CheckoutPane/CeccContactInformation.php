<?php

namespace Drupal\cecc_checkout\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\ContactInformation;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\telephone_formatter\Formatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the contact information pane with phone and extension.
 *
 * @CommerceCheckoutPane(
 *   id = "cecc_contact_information",
 *   label = @Translation("Contact information"),
 *   default_step = "order_information",
 *   wrapper_element = "fieldset",
 * )
 */
class CeccContactInformation extends ContactInformation {

  /**
   * The shipping order manager service.
   *
   * @var \Drupal\telephone_formatter\Formatter
   */
  protected $telephoneFormatter;

  /**
   * Constructs a new CheckoutPaneBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface $checkout_flow
   *   The parent checkout flow.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\telephone_formatter\Formatter $telephone_formatter
   *   The telephone formatter service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    CheckoutFlowInterface $checkout_flow,
    EntityTypeManagerInterface $entity_type_manager,
    Formatter $telephone_formatter) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $checkout_flow, $entity_type_manager);
    $this->telephoneFormatter = $telephone_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    CheckoutFlowInterface $checkout_flow = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $checkout_flow,
      $container->get('entity_type.manager'),
      $container->get('telephone_formatter.formatter')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function isVisible() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    $phone = $this->order->get('field_phone_number')->value;
    $phone = !empty($phone) ? $this->telephoneFormatter
      ->format($phone, 2, 'US') : NULL;
    $extension = $this->order->get('field_extension')->isEmpty() ?
    NULL : ' Ext. ' . $this->order->get('field_extension')->value;

    $build = [
      'email' => [
        '#type' => 'item',
        '#title' => $this->t('Email address:'),
        '#plain_text' => $this->order->getEmail(),
      ],
    ];

    if ($phone) {
      $build['phone'] = [
        '#type' => 'item',
        '#title' => $this->t('Phone number:'),
        '#plain_text' => $phone . $extension,
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#default_value' => $this->order->getEmail(),
      '#required' => TRUE,
    ];

    if ($this->configuration['double_entry']) {
      $pane_form['email_confirm'] = [
        '#type' => 'email',
        '#title' => $this->t('Confirm email'),
        '#default_value' => $this->order->getEmail(),
        '#required' => TRUE,
      ];
    }

    $pane_form['phone'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'container-inline',
          'contact-phone',
        ],
      ],
    ];

    $pane_form['phone']['phone_number'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone Number'),
      '#default_value' => $this->order->get('field_phone_number')->value,
      '#required' => FALSE,
      '#placeholder' => 'ex. "555-555-555"',
    ];

    $pane_form['phone']['extension'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Extension'),
      '#default_value' => $this->order->get('field_extension')->value,
      '#required' => FALSE,
      '#placeholder' => 'ex. "1234"',
    ];

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    if ($this->configuration['double_entry'] && $values['email'] != $values['email_confirm']) {
      $form_state->setError($pane_form, $this->t('The specified emails do not match.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    $this->order->setEmail($values['email']);
    $this->order->set('field_phone_number', $values['phone']['phone_number']);
    $this->order->set('field_extension', $values['phone']['extension']);
  }

}
