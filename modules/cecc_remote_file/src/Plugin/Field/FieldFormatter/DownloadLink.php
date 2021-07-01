<?php

namespace Drupal\cecc\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\media\Entity\Media;

/**
 * Plugin implementation of the download_link formatter.
 *
 * @FieldFormatter(
 *   id = "cecc_download_link",
 *   module = "cecc_remote_file",
 *   label = @Translation("Download Link"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class DownloadLink extends FormatterBase {

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

    foreach ($items as $delta => $item) {

      // Get the media item.
      $media_id = $item->getValue()['target_id'];
      $media_item = Media::load($media_id);

      try {
        $remoteFile = $media_item->get('field_media_remote_file');

        if ($remoteFile->isEmpty()) {
          return [];
        }

        $path = $remoteFile->value;

        $fileSize = $media_item->get('field_remote_file_size')->isEmpty() ?
        $media_item->getSource()->getMetadata($media_item, 'filesize')
        : $media_item->get('field_remote_file_size')->value;

        $elements[$delta] = [
          '#theme' => 'cecc_download_link',
          '#link_url' => $path,
          '#link_alt' => $media_item->getName(),
          '#file_size' => format_size($fileSize),
        ];

      }
      catch (\InvalidArgumentException $e) {
        \Drupal::logger('publication_ordering')->error($e->getMessage());
      }
    }

    return $elements;
  }

}
