<?php

namespace Drupal\cecc\Service;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form alterations and validators.
 */
interface FormHelperInterface {

  /**
   * Alter forms.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\core\Form\FormStateInterface $formState
   *   The forms state.
   * @param string $formId
   *   The form ID.
   */
  public function alterForms(array &$form, FormStateInterface $formState, $formId);

  /**
   * Checks form base ids and calls appropriate form.
   *
   * @param array $form
   *   Reference to the form array.
   */
  public function negotiateForms(array &$form);

  /**
   * Get the selected variation.
   *
   * @param \Drupal\core\Form\FormStateInterface $formState
   *   The forms state.
   *
   * @return \Drupal\commerce_product\Entity\ProductVariationInterface
   *   The product variation.
   */
  public static function getSelectedVariation(FormStateInterface $formState);

  /**
   * Get the selected variation.
   *
   * @param \Drupal\core\Form\FormStateInterface $formState
   *   The forms state.
   *
   * @return \Drupal\commerce_product\Entity\ProductInterface
   *   The product.
   */
  public static function getSelectedProduct(FormStateInterface $formState);

}
