<?php

namespace Drupal\cecc_remote_file\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\media\Entity\Media;

/**
 * Plugin implementation of the download_link formatter.
 *
 * @FieldFormatter(
 *   id = "cecc_remote_download_link",
 *   module = "cecc_remote_file",
 *   label = @Translation("Remote Download Link"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class DownloadLink extends EntityReferenceFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Displays the remote media as a link.');
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
        return (in_array('remote_file', $targetBundles));
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    /** @var \Drupal\media\Entity\Media[] $entities */
    $entities = $this->getEntitiesToView($items, $langcode);

    foreach ($entities as $media) {
      /** @var \Drupal\commerce_product\Entity\Product $product */
      $product = $media->_referringItem->getEntity();

      $remoteFile = $media->get('field_media_cecc_remote_file');

      $path = $remoteFile->value;

      $fileSize = $media->getSource()->getMetadata($media, 'filesize');

      $elements[] = [
        '#theme' => 'cecc_remote_download_link',
        '#product_title' => $product->get('field_cecc_display_title')->value,
        '#product_url' => $path,
        '#link_alt' => $media->getName(),
        '#file_size' => format_size($fileSize),
      ];
    }

    return $elements;
  }

}
