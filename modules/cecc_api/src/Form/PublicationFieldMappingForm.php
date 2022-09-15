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
   * Gets field definitions.
   *
   * @param string $entity_id
   *   The entity id.
   *
   * @return array
   *   Returns array [field_name] = label.
   */
  private function getFieldDefinitions($entity_id) {
    $fields = [];
    $config = $this->configFactory()->get('cecc_publication.settings');
    $commerce_product_type = $config->get('commerce_product_type');
    $definitions = $this->entityFieldManager
      ->getFieldDefinitions($entity_id, $commerce_product_type);

    foreach ($definitions as $defintion) {
      $fields[$defintion->getName()] = $defintion->getLabel();
    }

    return $fields;

  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('cecc_api.publication_field_mapping');

    $publicationFields = $this->getFieldDefinitions('commerce_product');
    $publicationVariationFields = $this->getFieldDefinitions('commerce_product_variation');

    $form['description'] = [
      '#type' => 'item',
      '#title' => $this->t('API Field Mapping'),
      '#markup' => $this->t('Allows mapping fields to specific values for CEC.'),
    ];

    $form['publication'] = [
      '#type' => 'details',
      '#title' => $this->t('Publication Display Fields'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['publication_variation'] = [
      '#type' => 'details',
      '#title' => $this->t('Publication Data Fields'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    foreach ($publicationFields as $name => $label) {
      $machineName = 'pf_' . $name;

      $form['publication'][$machineName] = [
        '#name' => $machineName,
        '#type' => 'textfield',
        '#title' => $this->t('Mapping for :label field', [
          ':label' => $label,
        ]),
        '#default_value' => $config->get($machineName),
      ];

      $form['publication'][$machineName . '_send'] = [
        '#name' => $machineName . '_send',
        '#type' => 'checkbox',
        '#title' => $this->t('Send as API value'),
        '#description' => $this->t('Check if field should be sent as an API value'),
        '#default_value' => $config->get($machineName . '_send') ?: 0,
      ];
    }

    foreach ($publicationVariationFields as $name => $label) {
      $machineName = 'pvf_' . $name;

      $form['publication_variation'][$machineName] = [
        '#name' => $machineName,
        '#type' => 'textfield',
        '#title' => $this->t('Mapping for :label field', [
          ':label' => $label,
        ]),
        '#default_value' => $config->get($machineName),
      ];

      $form['publication_variation'][$machineName . '_send'] = [
        '#name' => $machineName . '_send',
        '#type' => 'checkbox',
        '#title' => $this->t('Send as API value'),
        '#description' => $this->t('Check if field should be sent as an API value'),
        '#default_value' => $config->get($machineName . '_send') ?: 0,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('cecc_api.publication_field_mapping');
    $publicationFields = $this->getFieldDefinitions('commerce_product');
    $publicationVariationFields = $this->getFieldDefinitions('commerce_product_variation');

    foreach ($publicationFields as $name => $label) {
      $machineName = 'pf_' . $name;
      $config
        ->set($machineName, $form_state->getValue($machineName))
        ->set($machineName . '_send', $form_state->getValue($machineName . '_send'));
    }

    foreach ($publicationVariationFields as $name => $label) {
      $machineName = 'pvf_' . $name;
      $config
        ->set($machineName, $form_state->getValue($machineName))
        ->set($machineName . '_send', $form_state->getValue($machineName . '_send'));
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
