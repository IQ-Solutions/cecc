<?php

namespace Drupal\cecc_checkout\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Adds customer questions to checkout summary.
 *
 * @CommerceCheckoutPane(
 *   id = "cecc_overlimit",
 *   label = @Translation("Over Limit Order Request"),
 *   default_step = "order_notes",
 *   wrapper_element = "fieldset",
 * )
 */
class OverLimit extends CheckoutPaneBase implements CheckoutPaneInterface {

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

    $pane_form['field_event_name'] = [
      '#type' => 'textfield',
      '#title' => 'Event name',
      '#default_value' => $this->order->get('field_event_name')->isEmpty() ?
      NULL : $this->order->get('field_event_name')->value,
    ];

    $pane_form['field_event_location'] = [
      '#type' => 'textfield',
      '#title' => 'Event location (state)',
      '#default_value' => $this->order->get('field_event_location')->isEmpty() ?
      NULL : $this->order->get('field_event_location')->value,
    ];

    $pane_form['field_cecc_over_limit_desc'] = [
      '#type' => 'textarea',
      '#title' => 'Description',
      '#default_value' => $this->order->get('field_cecc_over_limit_desc')->isEmpty() ?
      NULL : $this->order->get('field_cecc_over_limit_desc')->value,
      '#required' => TRUE,
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

    if (!$this->order->get('field_cecc_over_limit_desc')->isEmpty()) {
      $build = [
        'summary_display' => [
          '#type' => 'container',
          '#title' => $this->t('Over Limit Order Request'),
        ],
      ];

      $build['summary_display']['field_event_name'] = [
        '#type' => 'item',
        '#title' => 'Event name',
        '#markup' => '<p>' . $this->order->get('field_event_name')->value . '</p>',
      ];

      $build['summary_display']['field_event_location'] = [
        '#type' => 'item',
        '#title' => 'Event location (state)',
        '#markup' => '<p>' . $this->order->get('field_event_location')->value . '</p>',
      ];

      $build['summary_display']['field_cecc_over_limit_desc'] = [
        '#type' => 'item',
        '#title' => $this->order->get('field_cecc_over_limit_desc')->getFieldDefinition()->getLabel(),
        '#markup' => '<p>' . $this->order->get('field_cecc_over_limit_desc')->value . '</p>',
      ];
    }

    return $build;
  }

}
