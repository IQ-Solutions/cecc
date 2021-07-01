<?php

namespace Drupal\cecc_publication\Plugin\Field\FieldWidget;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'commerce_quantity' widget.
 *
 * @FieldWidget(
 *   id = "cecc_quantity_select",
 *   label = @Translation("Quantity Select"),
 *   field_types = {
 *     "decimal",
 *     "integer",
 *   }
 * )
 */
class QuantitySelectWidget extends WidgetBase {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  public $renderer;

  /**
   * The entity type manager service.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  public $entityTypeManager;

  /**
   * Drupal config service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a WidgetBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    RendererInterface $renderer,
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->renderer = $renderer;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('renderer'),
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'show_label' => 0,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['show_label'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show element label'),
      '#default_value' => $this->getSetting('show_label'),
      '#description' => $this->t('The quantity label is shown'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $showLabel = $this->getSetting('show_label');

    if (!empty($showLabel)) {
      $summary[] = $this->t('Quantity Label is shown');
    }
    else {
      $summary[] = $this->t('Quantity label is hidden');
    }

    return $summary;
  }

  /**
   * {@inheritDoc}
   */
  public function formElement(
    FieldItemListInterface $items,
    $delta,
    array $element,
    array &$form,
    FormStateInterface $form_state) {

    $value = isset($items[$delta]->value) ? $items[$delta]->value : NULL;

    $selected_variation_id = $form_state->get('selected_variation');

    if (!empty($selected_variation_id)) {
      /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $selected_variation */
      $selected_variation = $this->entityTypeManager->getStorage('product_variation')
        ->load($selected_variation_id);
    }
    else {
      /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
      $product = $form_state->get('product');
      $selected_variation = $product->getDefaultVariation();
    }

    $options = [];
    $maxValue = 50;

    if ($selected_variation->hasField('field_maximum_order_amount')) {
      $quantityLimitOutput = NULL;

      if (!$selected_variation->get('field_maximum_order_amount')->isEmpty()) {
        $maxValue = $selected_variation->get('field_maximum_order_amount')->value;
      }

      $quantityLimitOutput = [
        '#theme' => 'cec_limit_display',
        '#quantity_limit' => $maxValue,
      ];

      $element['#suffix'] = $this->renderer->render($quantityLimitOutput)->__toString();
    }

    $i = 0;

    while ($i <= $maxValue) {
      $options[$i] = $i;

      if ($i === 0) {
        //$options[0] .= ' (Delete)';
      }

      if ($i < 10) {
        $i++;
      }
      elseif ($i >= 10 && $i < 25) {
        $i += 5;
      }
      elseif ($i >= 25) {
        $i += 25;
      }
    }

    $options[$maxValue + 1] = $maxValue . '+';

    $element += [
      '#type' => 'select',
      '#default_value' => $value,
      '#options' => $options,
    ];

    if ($this->getSetting('showLabel') == 0) {
      unset($element['#title']);
    }

    return ['value' => $element];
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getName() === 'quantity';
  }

}
