<?php

namespace Drupal\cecc_checkout\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Adds customer questions to checkout summary.
 *
 * @CommerceCheckoutPane(
 *   id = "cecc_customer_questions",
 *   label = @Translation("Additional Information"),
 *   default_step = "order_information",
 *   wrapper_element = "fieldset",
 * )
 */
class CustomerQuestions extends CheckoutPaneBase implements CheckoutPaneInterface {

  /**
   * {@inheritDoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $fields = $this->order->getFields();

    foreach ($fields as $field_name => $field) {
      $field_definition = $field->getFieldDefinition();
      $field_settings = $field->getSettings();

      if (isset($field_settings['allowed_values'])) {
        $pane_form[$field_name] = [
          '#type' => 'select',
          '#title' => $field_definition->getLabel(),
          '#options' => $field_settings['allowed_values'],
          '#empty_option' => '- Select a value -',
          '#default_value' => $field->isEmpty() ? NULL : $field->value,
          '#required' => TRUE,
        ];
      }
    }

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $value = $form_state->getValue($pane_form['#parents']);
    $fields = $this->order->getFields();

    foreach ($fields as $field_name => $field) {
      $field_settings = $field->getSettings();
      if (isset($field_settings['allowed_values'])) {
        $this->order->set($field_name, $value[$field_name]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    $fields = $this->order->getFields();

    $build = [
      'summary_display' => [
        '#type' => 'container',
        '#title' => $this->t('Additional Information'),
      ],
    ];

    foreach ($fields as $field_name => $field) {
      $field_settings = $field->getSettings();
      if (!$field->isEmpty()) {
        if (isset($field_settings['allowed_values'])) {
          $build['summary_display'][$field_name] = [
            '#type' => 'item',
            '#title' => $field->getFieldDefinition()->getLabel(),
            '#markup' => '<p>' . $field->value . '</p>',
          ];
        }
      }
    }

    return $build;
  }

}
