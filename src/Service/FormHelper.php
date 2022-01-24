<?php

namespace Drupal\cecc\Service;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowBase;
use Drupal\Component\Utility\Number;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
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
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

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
    RequestStack $requestStack,
    ModuleHandlerInterface $moduleHandler
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->requestStack = $requestStack;
    $this->moduleHandler = $moduleHandler;
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
   * Gets the form id from the base form id or the form id.
   *
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state object.
   * @param string $formId
   *   The form id.
   *
   * @return string
   *   The form id.
   */
  public static function getFormId(FormStateInterface $formState, $formId) {
    $formObject = $formState->getFormObject();
    $baseFormId = $formId;

    if ($formObject instanceof EntityForm || $formObject instanceof CheckoutFlowBase) {
      $baseFormId = $formObject->getBaseFormId();
    }

    return $baseFormId;
  }

  /**
   * {@inheritDoc}
   */
  public function alterForms(array &$form, FormStateInterface $formState, $formId) {
    $this->formObject = $formState->getFormObject();
    $this->formId = $formId;
    $this->formState = $formState;
    $this->baseFormId = self::getFormId($formState, $formId);

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
        '\Drupal\cecc\Service\FormHelper::validateNumber',
      ];
    }
  }

  /**
   * {@inheritDoc}
   */
  public function negotiateForms(array &$form) {
    switch ($this->baseFormId) {
      case 'views_form_cecc_cart_form_default':
        $this->alterCartPage($form);
        break;

      case 'commerce_checkout_flow':
      case 'commerce_checkout_flow_multistep_default':
        $this->alterCheckout($form);
        break;

      case 'commerce_order_item_add_to_cart_form':
        $this->alterCartForm($form);
        break;

      case 'user_form':
        $this->alterUserRegistrationForm($form);
        break;

      case 'user_login_form':
        $this->alterUserLoginForm($form);
        break;

      case 'user_pass':
        $this->alterUserPassForm($form);
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

      $form['actions']['continue_shopping'] = [
        '#type' => 'link',
        '#title' => $this->t('Continue Shopping'),
        '#url' => Url::fromRoute('view.publication_search.page_1'),
        '#weight' => 0,
        '#attributes' => [
          'class' => [
            'usa-button',
            'usa-button--outline',
          ],
        ],
      ];

      $form['actions']['submit']['#weight'] = 1;
      $form['actions']['checkout']['#weight'] = 2;
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

    if ($stepId == 'review') {

      $form['actions']['next']['#value'] = $this->t('Complete Checkout');

      if ($this->moduleHandler->moduleExists('captcha') && $this->moduleHandler->moduleExists('recaptcha')) {
        $form['captcha'] = [
          '#type' => 'captcha',
          '#captcha_type' => 'recaptcha/reCAPTCHA',
          '#prefix' => $this->t('<span class="text-bold font-ui-lg">Captcha</span><br>This question is for testing whether or not you are a human visitor and to prevent automated spam submissions.'),
        ];
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
    $ceccSettings = $this->configFactory->get('cecc.settings');
  }

  /**
   * Alters the user registration form.
   *
   * @param array $form
   *   The form array.
   */
  private function alterUserRegistrationForm(array &$form) {
    $form['account']['name']['#required'] = FALSE;
    $form['account']['name']['#access'] = FALSE;
    array_unshift($form['#validate'], '\Drupal\cecc\Service\FormHelper::prepareRegistrationFormValues');
    $form['#validate'][] = '\Drupal\cecc\Service\FormHelper::registerPostValidate';
    $form['account']['email']['#title'] = $this->t('Email Address');
    $form['actions']['submit']['#value'] = $this->t('Create New Account');
    $form['#title'] = $this->t('Create New Account');
  }

  /**
   * Alters the user registration form.
   *
   * @param array $form
   *   The form array.
   */
  private function alterUserLoginForm(array &$form) {
    $form['#title'] = $this->t('Log In');
    $form['pass']['#description'] = $this->t('Enter the password that accompanies your account.');
    $form['actions']['submit']['#value'] = $this->t('Log In');
  }

  /**
   * Alters the user password form.
   *
   * @param array $form
   *   The form array.
   */
  private function alterUserPassForm(array &$form) {
    $form['#title'] = $this->t('Reset Your Password');
    $form['name']['#title'] = $this->t('Email Address');
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
  public static function validateEmail(array &$element, FormStateInterface $form_state, array &$complete_form) {
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

  /**
   * Copy the 'mail' field to the 'name' field before form validation.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public static function prepareRegistrationFormValues(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('mail');
    $form_state->setValue('name', $email);
  }

  /**
   * Removes errors related to the name field on the registration form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public static function registerPostValidate(array &$form, FormStateInterface $form_state) {
    $errors = $form_state->getErrors();
    unset($errors['name']);
    $form_state->clearErrors();

    foreach ($errors as $field => $value) {
      $form_state->setErrorByName($field, $value);
    }
  }

}
