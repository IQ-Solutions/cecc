<?php

namespace Drupal\cecc_cart\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\commerce_product\Plugin\Field\FieldFormatter\AddToCartFormatter;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'cecc_cart_ajax' formatter.
 *
 * @FieldFormatter(
 *   id = "cecc_cart_ajax",
 *   label = @Translation("Ajax add to cart form"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class AjaxAddToCartFormatter extends AddToCartFormatter {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $form['combine'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Combine order items containing the same product variation.'),
      '#description' => $this->t('The order item type, referenced product variation, and data from fields exposed on the Ajax Add to Cart form must all match to combine.'),
      '#default_value' => $this->getSetting('combine'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $elements[0]['cecc_cart_ajax_form'] = [
      '#lazy_builder' => [
        'cecc_cart.lazy_builders:ajaxAddToCartForm', [
          $items->getEntity()->id(),
          $this->viewMode,
          $this->getSetting('combine'),
        ],
      ],
      '#create_placeholder' => TRUE,
    ];

    return $elements;
  }

}
