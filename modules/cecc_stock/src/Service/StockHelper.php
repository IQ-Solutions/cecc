<?php

namespace Drupal\cecc_stock\Service;

use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Stock form alterations and validators.
 */
class StockHelper {
  use StringTranslationTrait;
  use MessengerTrait;

  /**
   * Stock Validation Service.
   *
   * @var \Drupal\cecc_stock\Service\StockValidation
   */
  protected $stockValidation;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal config service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * The form object.
   *
   * @var \Drupal\core\Form\FormInterface
   */
  protected $formObject;

  /**
   * The form state object.
   *
   * @var \Drupal\Core\Form\FormStateInterface
   */
  protected $formState;

  /**
   * The form Id.
   *
   * @var string
   */
  protected $formId;

  /**
   * The base form id.
   *
   * @var string
   */
  protected $baseFormId;

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructor method.
   *
   * @param \Drupal\cecc_stock\Service\StockValidation $stockValidation
   *   The stock validation service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack service.
   */
  public function __construct(
    StockValidation $stockValidation,
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    RequestStack $requestStack
  ) {
    $this->stockValidation = $stockValidation;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->requestStack = $requestStack;
  }

  /**
   * Alter catalog forms for the stock module.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\core\Form\FormStateInterface $formState
   *   The forms state.
   * @param string $formId
   *   The form ID.
   */
  public function alterCatalogForms(array &$form, FormStateInterface $formState, $formId): void {
    $this->formObject = $formState->getFormObject();
    $this->formId = $formId;
    $this->formState = $formState;

    if (!method_exists($this->formObject, 'getBaseFormId')) {
      return;
    }

    $this->baseFormId = $this->formObject->getBaseFormId();

    switch ($this->baseFormId) {
      case 'views_form_cecc_cart_form_default':
        $this->alterCartPage($form);
        break;

      case 'commerce_order_item_po_ajax_add_to_cart_form':
      case 'commerce_order_item_add_to_cart_form':
        $this->alterCartForm($form);
        break;

      case 'commerce_checkout_flow':
        $this->alterCheckout($form);
        break;
    }
  }

  /**
   * Alter cart page form.
   *
   * @param array $form
   *   The form array.
   */
  private function alterCartPage(array &$form): void {
    /** @var \Drupal\views\ViewExecutable $view */
    $view = reset($this->formState->getBuildInfo()['args']);

    if ($view->storage->get('tag') == 'commerce_cart_form' && !empty($view->result)) {
      $order_id = $view->args[0];
      /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
      $order = $this->entityTypeManager->getStorage('commerce_order')
        ->load($order_id);

      $orderItems = $order->getItems();

      foreach ($orderItems as $index => $orderItem) {
        $purchasedEntity = $orderItem->getPurchasedEntity();

        if (!$purchasedEntity) {
          // Not every order item has a purchased entity.
          continue;
        }

        $quantityLimit = $purchasedEntity->get('field_cecc_order_limit')->value;
        $quantity = $orderItem->getQuantity();

        if (!empty($quantityLimit)) {
          $overLimit = $quantity > $quantityLimit;

          //$form['edit_quantity'][$index]['#max'] = $purchasedEntity->get('field_cecc_order_limit')->value;

          $form['edit_quantity'][$index]['#suffix'] = $this->t('<span>Quantity Limit: %limit</span>', [
            '%limit' => $purchasedEntity->get('field_cecc_order_limit')->value,
          ]);

          if ($overLimit) {
            $this->messenger()->addWarning($this->t('%label is over the quantity limit of %limit.', [
              '%label' => $purchasedEntity->getOrderItemTitle(),
              '%limit' => $quantityLimit,
            ]));
          }
        }
      }

      // Force a check to display the stock state to the user.
      $request_method = $this->requestStack->getCurrentRequest()->getMethod();

      // If a GET e.g. not a submit/post.
      if ($request_method == 'GET') {
        $this->stockValidation->isOrderInStock($order_id);
      }
    }
  }

  /**
   * Alter cart form.
   *
   * @param array $form
   *   The form array.
   */
  private function alterCartForm(array &$form) {
    // Get the product variation.
    $selected_variation_id = $this->formState->get('selected_variation');

    if (!empty($selected_variation_id)) {
      /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $selected_variation */
      $selected_variation = $this->entityTypeManager->getStorage('product_variation')
        ->load($selected_variation_id);
    }
    else {
      /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
      $product = $this->formState->get('product');
      $selected_variation = $product->getDefaultVariation();
    }

    // Add a form validate needed for the add to cart action.
    $form['#validate'] = array_merge($form['#validate'], [
      '\Drupal\cecc_stock\Service\StockHelper::validateAddToCart',
    ]);

    // Check if in stock.
    $instock = $this->stockValidation->checkProductStock($selected_variation);

    if (!$instock) {
      $form['actions']['submit']['#value'] = $this->t('Out of Stock');
      $form['actions']['submit']['#disabled'] = TRUE;

      // If quantity is visible.
      if (isset($form['quantity'])) {
        $form['quantity']['#disabled'] = TRUE;
        $form['quantity']['#access'] = FALSE;
      }
    }
  }

  /**
   * Alter cart form.
   *
   * @param array $form
   *   The form array.
   */
  private function alterCheckout(array &$form) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $this->formObject->getOrder();

    if ($form['#step_id'] != 'complete' && !$this->stockValidation->isOrderInStock($order->id())) {
      // Redirect back to cart.
      $response = new RedirectResponse(Url::fromRoute('commerce_cart.page')->toString());
      $response->send();
    }

    // Add a submit validate.
    $form['#validate'] = array_merge($form['#validate'], [
      '\Drupal\cecc_stock\Service\StockHelper::validateCheckout',
    ]);
  }

  /**
   * Validates the add to cart form submit.
   *
   * @param array $form
   *   Nested array of form elements that comprise the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateAddToCart(
    array $form,
    FormStateInterface $form_state
  ) {
    // Get add to cart quantity.
    $values = $form_state->getValues();

    if (isset($values['quantity'])) {
      $quantity = $values['quantity'][0]['value'];
    }
    else {
      $quantity = 1;
    }

    // Load the product variation.
    $variation_id = $values['purchased_entity'][0]['variation'];
    /** @var \Drupal\commerce\PurchasableEntityInterface $purchasedEntity */
    $purchasedEntity = ProductVariation::load($variation_id);

    /** @var \Drupal\cecc_stock\Service\StockValidation $stockValidation */
    $stockValidation = \Drupal::service('cecc_stock.stock_validation');
    $stockConfig = \Drupal::config('cecc_stock.settings');

    if ($stockValidation->isCartOverQuantityLimit($variation_id, $quantity)) {
      $form_state->setError($form, t($stockConfig->get('over_limit_text'), [
        '%label' => $purchasedEntity->getOrderItemTitle(),
        '%limit' => $purchasedEntity->get('field_cecc_order_limit')->value,
      ]));
    }
  }

  /**
   * Validate the checkout form submit.
   *
   * @param array $form
   *   Nested array of form elements that comprise the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Drupal\commerce\Response\NeedsRedirectException
   */
  public static function validateCheckout(
    array $form,
    FormStateInterface $form_state
  ) {

    /** @var \Drupal\cecc_stock\Service\StockValidation $stockValidation */
    $stockValidation = \Drupal::service('cecc_stock.stock_validation');

    /** @var \Drupal\Core\Form\FormInterface $form_object */
    $form_object = $form_state->getFormObject();

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $form_object->getOrder();

    if (!$stockValidation->isOrderInStock($order->id(), FALSE)) {
      $cart_page = Url::fromRoute('commerce_cart.page', [], ['absolute' => TRUE]);
      \Drupal::messenger()->addError('One or more Items are out of stock. Checkout canceled!');
      throw new NeedsRedirectException($cart_page->toString());
    }
  }

  /**
   * Validate the cart page submit.
   *
   * @param array $form
   *   Nested array of form elements that comprise the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public static function validateCartPage(
    array $form,
    FormStateInterface $form_state
  ) {
    $triggering_element = $form_state->getTriggeringElement();
    // If triggered by a line item delete.
    if (isset($triggering_element['#remove_order_item']) && $triggering_element['#remove_order_item']) {
      // No need to validate.
      return;
    }

    /** @var \Drupal\cecc_stock\Service\StockValidation $stockValidation */
    $stockValidation = \Drupal::service('cecc_stock.stock_validation');

    $values = $form_state->getValues();
    if (isset($values['edit_quantity'])) {
      $quantities = $values['edit_quantity'];
    }
    else {
      $quantities = [];
    }

    /** @var \Drupal\views\ViewExecutable $view */
    $view = reset($form_state->getBuildInfo()['args']);
    // Get the order ID from the view argument.
    $order_id = $view->argument['order_id']->value[0];
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = \Drupal::entityTypeManager()
      ->getStorage('commerce_order')
      ->load($order_id);

    foreach ($order->getItems() as $id => $order_item) {
      $purchased_entity = $order_item->getPurchasedEntity();
      if (!$purchased_entity) {
        // Not every order item has a purchased entity.
        continue;
      }

      $name = $purchased_entity->getTitle();
      if (isset($quantities) && isset($quantities[$id])) {
        $qty = $quantities[$id];
      }
      else {
        $qty = 1;
      }

      if ($stockValidation->checkProductStock($purchased_entity, $qty)) {
        $form_state->setError(
          $form['edit_quantity'][$id],
          // t('Sorry we only have %qty in stock', array('%qty' => $stock_level))
          t("The maximum quantity for %name that can be ordered is %qty.", [
            '%name' => $name,
            '%qty' => $purchased_entity->get('field_cecc_stock')->value,
          ])
        );
      }
    }
  }

}
