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
 *   label = @Translation("Item Over Limit Notes"),
 *   default_step = "order_notes",
 *   wrapper_element = "fieldset",
 * )
 */
class OverLimit extends CheckoutPaneBase implements CheckoutPaneInterface {

  /**
   * {@inheritDoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {

    $pane_form['field_cecc_over_limit_desc'] = [
      '#type' => 'textarea',
      '#title' => $this->order->get('field_cecc_over_limit_desc')->getFieldDefinition()->getLabel(),
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
          '#title' => $this->t('Item Over Limit Notes'),
        ],
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
