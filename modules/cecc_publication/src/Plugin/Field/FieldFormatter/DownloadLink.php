<?php

namespace Drupal\cecc_publication\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the download_link formatter.
 *
 * @FieldFormatter(
 *   id = "cecc_download_link",
 *   module = "cecc_publication",
 *   label = @Translation("Download Link"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class DownloadLink extends FormatterBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  public $entityTypeManager;

  /**
   * An array of mimetypes and labels.
   *
   * @var array
   */
  private $mimeTypeList = [
    'application/pdf' => 'PDF',
    'application/epub+zip' => 'EPUB',
    'application/x-mobipocket-ebook' => 'MOBI',
  ];

  /**
   * Constructs a FormatterBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manage service.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $label,
      $view_mode,
      $third_party_settings);

    $this->entityTypeManager = $entity_type_manager;
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
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Displays the media as a link.');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {

    if ($field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'media') {
      $handlerSettings = $field_definition->getSetting('handler_settings');

      if (!empty($handlerSettings)) {
        $targetBundles = $handlerSettings['target_bundles'];
        return (in_array('document', $targetBundles));
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {

      if (!isset($item->getValue()['target_id'])) {
        continue;
      }

      // Get the media item.
      $media_id = $item->getValue()['target_id'];
      /** @var \Drupal\media\Entity\Media $mediaItem */
      $mediaItem = $this->entityTypeManager->getStorage('media')->load($media_id);

      /** @var \Drupal\file\Entity\File $file */
      $file = $mediaItem->get('field_media_document')->entity;

      if (!$file) {
        return [];
      }

      $path = $file->createFileUrl();

      $fileSize = $file->getSize();
      $fileType = isset($this->mimeTypeList[$file->getMimeType()]) ?
        $this->mimeTypeList[$file->getMimeType()] : NULL;

      $elements[$delta] = [
        '#theme' => 'cecc_download_link',
        '#link_url' => $path,
        '#link_alt' => $mediaItem->getName(),
        '#file_type' => $fileType,
        '#file_size' => format_size($fileSize),
      ];
    }

    return $elements;
  }

}
