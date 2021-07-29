<?php

namespace Drupal\cecc_api\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Manages base CECC API config.
 */
class PublicationFieldMappingForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Instantiates Publication Field Mapping form.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config object factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);

    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritDoc}
   */
  protected function getEditableConfigNames() {
    return [
      'cecc_api.publication_field_mapping',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return "cecc_api_publication_field_mapping";
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('cecc_api.publication_field_mapping');

    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $publicationFieldDefinitions */
    $publicationFieldDefinitions = $this->entityFieldManager
      ->getFieldDefinitions('commerce_product', 'cecc_publication');

    $publicationFields = [];

    foreach ($publicationFieldDefinitions as $publicationFieldDefinition) {
      $publicationFields[$publicationFieldDefinition->getName()] = $publicationFieldDefinition->getLabel();
    }

    foreach ($publicationFields as $name => $label) {
      $form[$name] = [
        '#type' => 'textfield',
        '#title' => $this->t('Mapping for :label field', [
          ':label' => $label,
        ]),
        '#default_value' => $config->get($name),
      ];

      $form[$name . '_send'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Send as API value'),
        '#description' => $this->t('Check if field should be sent as an API value'),
        '#default_value' => $config->get('enable_api') ?: 0,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('cecc_api.settings');
    $config
      ->set('enable_api', $form_state->getValue('enable_api'))
      ->set('agency', $form_state->getValue('agency'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('base_api_url', $form_state->getValue('base_api_url'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
