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
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form)
  {

    $pane_form['field_shipping_vendor'] = [
      '#type' => 'select',
      '#title' => $this->order->get('field_shipping_vendor')->getFieldDefinition()->getLabel(),
      '#options' => $this->order->get('field_shipping_vendor')->getSetting('allowed_values'),
      '#empty_option' => '- Select a value -',
      '#default_value' => $this->order->get('field_shipping_vendor')->isEmpty() ?
      NULL : $this->order->get('field_shipping_vendor')->value,
    ];

    $pane_form['field_shipping_account_number'] = [
      '#type' => 'textfield',
      '#title' => $this->order->get('field_shipping_account_number')->getFieldDefinition()->getLabel(),
      '#default_value' => $this->order->get('field_shipping_account_number')->isEmpty() ?
      NULL : $this->order->get('field_shipping_account_number')->value,
    ];

    $pane_form['setting'] = [
      '#type' => 'select',
      '#title' => $this->order->get('field_setting')->getFieldDefinition()->getLabel(),
      '#options' => $this->order->get('field_setting')->getSetting('allowed_values'),
      '#empty_option' => '- Select a value -',
      '#default_value' => $this->order->get('field_setting')->isEmpty() ?
      NULL : $this->order->get('field_setting')->value,
      '#required' => TRUE,
    ];

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $value = $form_state->getValue($pane_form['#parents']);
    $this->order->set('field_shipping_vendor', $value['field_shipping_vendor']);
    $this->order->set('field_shipping_account_number', $value['field_shipping_account_number']);
    $this->order->set('field_setting', $value['setting']);
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    $build = [
      'summary_display' => [
        '#type' => 'container',
        '#title' => $this->t('Additional Information'),
      ],
    ];

    if (!$this->order->get('field_shipping_vendor')->isEmpty()) {

      $build['summary_display']['field_shipping_vendor'] = [
        '#type' => 'item',
        '#title' => $this->order->get('field_shipping_vendor')->getFieldDefinition()->getLabel(),
        '#markup' => '<p>' . $this->order->get('field_shipping_vendor')->value . '</p>',
      ];
    }

    if (!$this->order->get('field_shipping_account_number')->isEmpty()) {

      $build['summary_display']['field_shipping_account_number'] = [
        '#type' => 'item',
        '#title' => $this->order->get('field_shipping_account_number')->getFieldDefinition()->getLabel(),
        '#markup' => '<p>' . $this->order->get('field_shipping_account_number')->value . '</p>',
      ];
    }

    if (!$this->order->get('field_setting')->isEmpty()) {

      $build['summary_display']['pub_setting'] = [
        '#type' => 'item',
        '#title' => $this->order->get('field_setting')->getFieldDefinition()->getLabel(),
        '#markup' => '<p>' . $this->order->get('field_setting')->value . '</p>',
      ];
    }

    return $build;
  }

}
