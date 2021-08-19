<?php

namespace Drupal\cecc_stock\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Plugin\views\field\UncacheableFieldHandlerTrait;

/**
 * Defines a form element for display order item notices.
 *
 * @ViewsField("order_item_over_limit")
 */
class OrderItemOverLimit extends FieldPluginBase {

  use UncacheableFieldHandlerTrait;

  /**
   * {@inheritdoc}
   */
  public function clickSortable() {
    return FALSE;
  }

  /**
   * Form constructor for the views form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function viewsForm(array &$form, FormStateInterface $form_state) {
    // Make sure we do not accidentally cache this form.
    $form['#cache']['max-age'] = 0;

    // The view is empty, abort.
    if (empty($this->view->result)) {
      unset($form['actions']);
      return;
    }

    $form['#attached'] = [
      'library' => ['commerce_cart/cart_form'],
    ];

    $form[$this->options['id']]['#tree'] = TRUE;

    foreach ($this->view->result as $row_index => $row) {
      /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
      $order_item = $this->getEntity($row);
      $purchasedEntity = $order_item->getPurchasedEntity();
      $quantityLimit = $purchasedEntity->get('field_cecc_order_limit')->value;
      $quantity = round($order_item->getQuantity());
      $isOverLimit = $quantity > $quantityLimit;

      $form[$this->options['id']][$row_index] = [
        '#type' => 'item',
        '#plain_text' => $isOverLimit ? 'Over Limit' : '',
        '#attributes' => [],
      ];
    }

  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing.
  }

}
