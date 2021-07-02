<?php

namespace Drupal\cecc_cart\Form;

use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_cart\Form\AddToCartForm;
use Drupal\commerce_cart\Form\AddToCartFormInterface;
use Drupal\commerce_order\Resolver\OrderTypeResolverInterface;
use Drupal\commerce_price\Resolver\ChainPriceResolverInterface;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\commerce_store\SelectStoreTrait;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityConstraintViolationListInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\cecc_cart\Helper\RefreshPageElements;
use Drupal\po_stock\Service\StockValidation;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the order item add to cart form.
 */
class AjaxAddToCartForm extends AddToCartForm implements AddToCartFormInterface {

  use SelectStoreTrait;

  /**
   * RefreshPageElementsHelper service.
   *
   * @var \Drupal\cecc_cart\Helper\RefreshPageElements
   */
  protected $refreshPageElementsHelper;

  /**
   * EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Stock validation Service.
   *
   * @var \Drupal\po_stock\Service\StockValidation
   */
  protected $stockValidation;

  /**
   * Drupal config service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new AddToCartForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   * @param \Drupal\commerce_cart\CartManagerInterface $cart_manager
   *   The cart manager.
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   *   The cart provider.
   * @param \Drupal\commerce_order\Resolver\OrderTypeResolverInterface $order_type_resolver
   *   The order type resolver.
   * @param \Drupal\commerce_store\CurrentStoreInterface $current_store
   *   The current store.
   * @param \Drupal\commerce_price\Resolver\ChainPriceResolverInterface $chain_price_resolver
   *   The chain base price resolver.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\cecc_cart\Helper\RefreshPageElements $refresh_page_elements_helper
   *   The RefreshPageElementsHelper service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Used to display the rendered product_variation entity.
   * @param \Drupal\po_stock\Service\StockValidation $stockValidation
   *   Stock validation service.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    CartManagerInterface $cart_manager,
    CartProviderInterface $cart_provider,
    OrderTypeResolverInterface $order_type_resolver,
    CurrentStoreInterface $current_store,
    ChainPriceResolverInterface $chain_price_resolver,
    AccountInterface $current_user,
    RefreshPageElements $refresh_page_elements_helper,
    EntityTypeManagerInterface $entity_type_manager,
    StockValidation $stockValidation,
    ConfigFactoryInterface $config_factory) {
    parent::__construct(
      $entity_repository,
      $entity_type_bundle_info,
      $time,
      $cart_manager,
      $cart_provider,
      $order_type_resolver,
      $current_store,
      $chain_price_resolver,
      $current_user);

    $this->refreshPageElementsHelper = $refresh_page_elements_helper;
    $this->entityTypeManager = $entity_type_manager;
    $this->stockValidation = $stockValidation;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('commerce_cart.cart_manager'),
      $container->get('commerce_cart.cart_provider'),
      $container->get('commerce_order.chain_order_type_resolver'),
      $container->get('commerce_store.current_store'),
      $container->get('commerce_price.chain_price_resolver'),
      $container->get('current_user'),
      $container->get('cecc_cart.refresh_page_elements_helper'),
      $container->get('entity_type.manager'),
      $container->get('po_stock.stock_validation'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setFormId($form_id) {
    $this->formId = $form_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['#attached']['library'][] = 'core/jquery';
    $form['#attached']['library'][] = 'core/drupal.ajax';
    $form['#attached']['library'][] = 'core/jquery.form';
    $form['#attached']['library'][] = 'cecc_cart/addToCart';
    $form['#disable_inline_form_errors'] = TRUE;

    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $orderItem */
    $orderItem = $this->getEntity();

    $purchasedEntity = $orderItem->getPurchasedEntity();
    $commerceConfig = $this->configFactory->get('publication_ordering.settings');
    $addToCartType = $commerceConfig->get('quantity_update_type');

    if ($addToCartType == 'cart') {
      $form['quantity']['widget'][0]['value']['#default_value'] = $this->stockValidation->getOrderedQuantity($purchasedEntity);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $wrapper_id = Html::getUniqueId($this->getFormId() . '-ajax-add-cart-wrapper');

    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $orderItem */
    $orderItem = $this->getEntity();

    $purchasedEntity = $orderItem->getPurchasedEntity();
    $commerceConfig = $this->configFactory->get('publication_ordering.settings');
    $addToCartType = $commerceConfig->get('quantity_update_type');
    $buttonText = $this->t('Add');

    if ($addToCartType == 'cart') {
      $buttonText = $this->t('Update');
    }

    $actions['submit'] = [
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
      '#type' => 'submit',
      '#value' => $buttonText,
      '#ajax' => [
        'callback' => '::refreshAddToCartForm',
        'wrapper' => $wrapper_id,
        'disable-refocus' => TRUE,
      ],
      '#attributes' => [
        'class' => [
          'button--add-to-cart',
          'use-ajax-submit',
        ],
      ],
    ];

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Remove button and internal Form API values from submitted values.
    $form_state->cleanValues();
    $this->entity = $this->buildEntity($form, $form_state);
    $this->updateChangedTime($this->entity);

    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $this->entity;
    /** @var \Drupal\commerce\PurchasableEntityInterface $purchased_entity */
    $purchased_entity = $order_item->getPurchasedEntity();

    $quantity = $order_item->getQuantity();

    $cart = $order_item->getOrder();

    if ($quantity > 0) {
      if (!$cart) {
        $order_type_id = $this->orderTypeResolver->resolve($order_item);
        $store = $this->selectStore($purchased_entity);
        $cart = $this->cartProvider->createCart($order_type_id, $store);
      }

      $this->entity = $this->cartManager->addOrderItem($cart, $order_item, $form_state->get(['settings', 'combine']));
    }
    else {
      $this->entity = $this->cartManager->removeOrderItem($cart, $order_item);
      $cart->save();
    }

    // Other submit handlers might need the cart ID.
    $form_state->set('cart_id', $cart->id());
  }

  /**
   * {@inheritdoc}
   *
   * Button-level validation handlers are highly discouraged for entity forms,
   * as they will prevent entity validation from running. If the entity is going
   * to be saved during the form submission, this method should be manually
   * invoked from the button-level validation handler, otherwise an exception
   * will be thrown.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $entity = parent::validateForm($form, $form_state);

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
    $purchasedEntity = $this->entityTypeManager->getStorage('commerce_product_variation')
      ->load($variation_id);

    $stockConfig = \Drupal::config('po_stock.settings');

    if ($this->stockValidation->isCartOverQuantityLimit($variation_id, $quantity)) {
      $message = $this->t($stockConfig->get('order_over_limit_text'), [
        '%label' => $purchasedEntity->getOrderItemTitle(),
        '%limit' => $purchasedEntity->get('field_maximum_order_amount')->value,
      ]);

      $form_state->setError($form, $message);
    }

    return $entity;
  }

  /**
   * Flags violations for the current form.
   *
   * If the entity form customly adds some fields to the form (i.e. without
   * using the form display), it needs to add its fields to array returned by
   * getEditedFieldNames() and overwrite this method in order to show any
   * violations for those fields; e.g.:
   * @code
   * foreach ($violations->getByField('name') as $violation) {
   *   $form_state->setErrorByName('name', $violation->getMessage());
   * }
   * parent::flagViolations($violations, $form, $form_state);
   * @endcode
   *
   * @param \Drupal\Core\Entity\EntityConstraintViolationListInterface $violations
   *   The violations to flag.
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function flagViolations(
    EntityConstraintViolationListInterface $violations,
    array $form,
    FormStateInterface $form_state) {
    // Flag entity level violations.
    foreach ($violations->getEntityViolations() as $violation) {
      /** @var \Symfony\Component\Validator\ConstraintViolationInterface $violation */
      $form_state->setError($form, $violation->getMessage());
    }
    // Let the form display flag violations of its fields.
    $this->getFormDisplay($form_state)->flagWidgetsErrorsFromViolations($violations, $form, $form_state);
  }

  /**
   * Refreshes the add to cart form.
   *
   * Fixes https://www.drupal.org/node/2905814
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The updated ajax response.
   */
  public function refreshAddToCartForm(array $form, FormStateInterface $form_state) {
    return $this->refreshPageElementsHelper
      ->updatePageElements($form, $form_state, '#ajax-errors-' . $this->getFormId())
      ->getResponse();
  }

}
