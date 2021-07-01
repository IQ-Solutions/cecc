<?php

namespace Drupal\cecc\Service;

use Drupal\Component\Utility\Number;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form alterations and validators.
 */
class FormHelper implements FormHelperInterface {
  use StringTranslationTrait;

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    RequestStack $requestStack
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritDoc}
   */
  public static function getSelectedVariation(FormStateInterface $formState) {
    $selected_variation_id = $formState->get('selected_variation');

    if (!empty($selected_variation_id)) {
      /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $selected_variation */
      $selected_variation = \Drupal::entityTypeManager()->getStorage('product_variation')
        ->load($selected_variation_id);
    }
    else {
      /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
      $product = self::getSelectedProduct($formState);
      $selected_variation = $product->getDefaultVariation();
    }

    return $selected_variation;
  }

  /**
   * {@inheritDoc}
   */
  public static function getSelectedProduct(FormStateInterface $formState) {
    return $formState->get('product');
  }

  /**
   * {@inheritDoc}
   */
  public function alterForms(array &$form, FormStateInterface $formState, $formId) {
    $this->formObject = $formState->getFormObject();
    $this->formId = $formId;
    $this->formState = $formState;

    if (!method_exists($this->formObject, 'getBaseFormId')) {
      return;
    }

    $this->baseFormId = $this->formObject->getBaseFormId();

    $this->alterFormElements($form);
    $this->negotiateForms($form);
  }

  /**
   * Make global form edits if element exist.
   *
   * @param array $form
   *   The referenced form array.
   */
  public function alterFormElements(array &$form) {
    if (isset($form['quantity'])) {
      $form['quantity']['widget'][0]['value']['#element_validate'] = [
        '\Drupal\publication_ordering\Service\FormHelper::validateNumber',
      ];
    }
  }

  /**
   * {@inheritDoc}
   */
  public function negotiateForms(array &$form) {
    switch ($this->baseFormId) {
      case 'commerce_checkout_flow':
        $this->alterCheckout($form);
        break;
    }
  }

  /**
   * Alter cart form.
   *
   * @param array $form
   *   The form array.
   */
  public function alterCheckout(array &$form) {
    $stepId = $form['#step_id'];

    if ($stepId == 'order_information') {
      $form['actions']['next']['#value'] = $this->t('Review Your Order');
    }

    if ($stepId == 'review') {
      $form['actions']['next']['#value'] = $this->t('Complete Checkout');
    }

    $form['actions']['next']['#suffix'] = Link::createFromRoute(
      'Back to Cart',
      'commerce_cart.page',
      [],
      [
        'attributes' => [
          'class' => ['link--previous'],
        ],
      ]
    )->toString();
  }

  /**
   * Number Validation override.
   *
   * @param mixed $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public static function validateNumber(&$element, FormStateInterface $form_state) {

    $value = $element['#value'];
    if ($value === '') {
      return;
    }

    $selectedVariation = self::getSelectedVariation($form_state);

    $name = empty($element['#title']) ? $element['#parents'][0] : $element['#title'];

    // Ensure the input is numeric.
    if (!is_numeric($value)) {
      $form_state->setError($element, t('%name must be a number.', ['%name' => $name]));
      return;
    }

    // Ensure that the input is greater than the #min property, if set.
    if (isset($element['#min']) && $value < $element['#min']) {
      $form_state->setError($element, t('%name%product_title must be higher than or equal to %min.', [
        '%name' => $name,
        '%min' => $element['#min'],
        '%product_title' => !empty($selectedVariation) ?
        ' for ' . $selectedVariation->getTitle() : '',
      ]));
    }

    // Ensure that the input is less than the #max property, if set.
    if (isset($element['#max']) && $value > $element['#max']) {
      $form_state->setError($element, t('%name%product_title must be lower than or equal to %max.', [
        '%name' => $name,
        '%max' => $element['#max'],
        '%product_title' => !empty($selectedVariation) ?
        ' for ' . $selectedVariation->getTitle() : '',
      ]));
    }

    if (isset($element['#step']) && strtolower($element['#step']) != 'any') {
      // Check that the input is an allowed multiple of #step (offset by #min if
      // #min is set).
      $offset = isset($element['#min']) ? $element['#min'] : 0.0;

      if (!Number::validStep($value, $element['#step'], $offset)) {
        $form_state->setError($element, t('%name is not a valid number.', ['%name' => $name]));
      }
    }
  }

  /**
   * Email validation override.
   *
   * @param array $element
   *   The element array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param array $complete_form
   *   The complete form array.
   */
  public static function validateEmail(&$element, FormStateInterface $form_state, &$complete_form) {
    $value = trim($element['#value']);

    if (empty($value)) {
      return;
    }

    $form_state->setValueForElement($element, $value);

    if (!\Drupal::service('email.validator')->isValid($value)
    || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
      $form_state->setError($element, t('The email address %mail is not valid.', ['%mail' => $value]));
    }
  }

}
