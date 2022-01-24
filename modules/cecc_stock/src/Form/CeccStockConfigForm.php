<?php

namespace Drupal\cecc_stock\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Manages base Publication Ordering API config.
 */
class CeccStockConfigForm extends ConfigFormBase {

  /**
   * {@inheritDoc}
   */
  protected function getEditableConfigNames() {
    return [
      'cecc_stock.settings',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return "cecc_stock_settings";
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('cecc_stock.settings');

    $form['description'] = [
      '#type' => 'item',
      '#title' => $this->t('Warning and info messages'),
      '#markup' => $this->t('Allows changing of various warning and info messages throughout the site.'),
    ];

    $form['hard_limit_order_quantity'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Limit ordering at quantity limit (Strict)'),
      '#description' => $this->t('When a product is at quantity limit and this is enabled, the <em>Add|Update</em> button will change to <em>View Your Cart</em> and display a "at quantity limit for the product" message.'),
      '#default_value' => !empty($config->get('hard_limit_order_quantity')) ?
      $config->get('hard_limit_order_quantity') : 0,
    ];

    $form['soft_limit_order_quantity'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Limit ordering at quantity limit'),
      '#description' => $this->t('When a product is at quantity limit and this is enabled, and additonal form entry will display on the cart.'),
      '#default_value' => !empty($config->get('soft_limit_order_quantity')) ?
      $config->get('soft_limit_order_quantity') : 0,
    ];

    $form['quantity_limit_messaging'] = [
      '#type' => 'details',
      '#title' => $this->t('Quantity Limit Messaging'),
    ];

    $form['over_available stock_messaging'] = [
      '#type' => 'details',
      '#title' => $this->t('Over Available Stock Messaging'),
    ];

    $form['quantity_limit_messaging']['over_limit_text'] = [
      '#name' => 'over_limit_text',
      '#type' => 'text_format',
      '#title' => $this->t('Quantity Over Limit Message'),
      '#description' => $this->t('Message shown when a user tries to order more than the allowed quantity.<br>
      <strong>Replacement text</strong>: <ul><li><em>%limit</em> = The Quantity limit for that product/publication</li>
      <li><em>%label</em>  = the name of the product/publication</li></ul>'),
      '#default_value' => $config->get('over_limit_text'),
    ];

    $form['quantity_limit_messaging']['order_over_limit_text'] = [
      '#name' => 'order_over_limit_text',
      '#type' => 'text_format',
      '#title' => $this->t('Quantity Over Limit Message'),
      '#description' => $this->t('Message shown when a tries to add more than the allowed quantity to their cart/order.<br>
      <strong>Replacement text</strong>: <ul>
      <li><em>%limit</em>  = The Quantity limit for that product/publication</li>
      <li><em>%label</em>  = the name of the product/publication</li></ul>'),
      '#default_value' => $config->get('order_over_limit_text'),
    ];

    $form['quantity_limit_messaging']['order_over_limit_warning_text'] = [
      '#name' => 'order_over_limit_warning_text',
      '#type' => 'text_format',
      '#title' => $this->t('Cart Over Limit Notification'),
      '#description' => $this->t('Message shown on cart page when an item is over limit'),
      '#default_value' => $config->get('order_over_limit_warning_text'),
    ];

    $form['over_available stock_messaging']['over_stock_level'] = [
      '#name' => 'over_stock_level',
      '#type' => 'text_format',
      '#title' => $this->t('Quantity Over Stock Level Message'),
      '#description' => $this->t('Message shown when a user tries to order more than the available stock.<br>
      <strong>Replacement text</strong>: <ul><li><em>%quantity</em> = The quanity added for that product/publication</li>
      <li><em>%label</em>  = the name of the product/publication</li></ul>'),
      '#default_value' => $config->get('over_stock_level'),
    ];

    $form['over_available stock_messaging']['order_over_stock_level'] = [
      '#name' => 'order_over_stock_level',
      '#type' => 'text_format',
      '#title' => $this->t('Order Over Stock Level Message'),
      '#description' => $this->t('Message shown when a tries to add more than the available stock to their cart/order.<br>
      <strong>Replacement text</strong>: <ul>
      <li><em>%quantity</em>  = The quanity added for that product/publication</li>
      <li><em>%label</em>  = the name of the product/publication</li></ul>'),
      '#default_value' => $config->get('order_over_stock_level'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('cecc_stock.settings')
      ->set('over_limit_text', $form_state->getValue('over_limit_text')['value'])
      ->set('order_over_limit_text', $form_state->getValue('order_over_limit_text')['value'])
      ->set('order_over_limit_warning_text', $form_state->getValue('order_over_limit_warning_text')['value'])
      ->set('over_stock_level', $form_state->getValue('over_stock_level')['value'])
      ->set('order_over_stock_level', $form_state->getValue('order_over_stock_level')['value'])
      ->set('hard_limit_order_quantity', $form_state->getValue('hard_limit_order_quantity'))
      ->set('soft_limit_order_quantity', $form_state->getValue('soft_limit_order_quantity'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
