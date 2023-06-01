<?php

namespace Drupal\cecc_publication\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

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
class DownloadLink extends EntityReferenceFormatterBase {

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
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'disposition' => ResponseHeaderBag::DISPOSITION_INLINE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $elements['rel'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add rel="nofollow" to links'),
      '#return_value' => 'nofollow',
      '#default_value' => $this->getSetting('rel'),
    ];
    $elements['target'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open link in new window'),
      '#return_value' => '_blank',
      '#default_value' => $this->getSetting('target'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Displays the media as a link.');
    $settings = $this->getSettings();
    if ($settings['disposition'] == ResponseHeaderBag::DISPOSITION_ATTACHMENT) {
      $summary[] = $this->t('Force "Save as..." dialog');
    }
    else {
      $summary[] = $this->t('Display media in browser.');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {

    // This formatter is only available for entity types that reference
    // media items whose source field types are file.
    $target_type = $field_definition->getFieldStorageDefinition()->getSetting('target_type');

    if ($target_type != 'media') {
      return FALSE;
    }

    $handler_settings = $field_definition->getSetting('handler_settings');

    if (!empty($handler_settings)) {
      $media_bundles = $handler_settings['target_bundles'];

      if (!isset($media_bundles)) {
        return FALSE;
      }

      return (in_array('remote_file', $media_bundles) || in_array('document', $media_bundles));
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    /** @var \Drupal\media\Entity\Media[] $entities */
    $entities = $this->getEntitiesToView($items, $langcode);


    foreach ($entities as $media) {
      $bundle = $media->bundle();
      /** @var \Drupal\commerce_product\Entity\Product $product */
      $product = $media->_referringItem->getEntity();

      if ($bundle == 'document') {
        /** @var \Drupal\file\Entity\File $file */
        $file = $media->field_media_document->entity;

        if (empty($file)) {
          continue;
        }

        $elements[] = $this->getDocumentElement($media, $file, $product);
      }
      elseif ($bundle == 'remote_file') {
        $elements[] = $this->getRemoteFileElement($media, $product);
      }
    }

    return $elements;
  }

  /**
   * Gets document media file.
   *
   * @param \Drupal\media\Entity\Media $media
   *   The media item.
   * @param \Drupal\commerce_product\Entity\Product $product
   *   The parent product.
   *
   * @return array
   *   The render array.
   */
  private function getRemoteFileElement($media, $product) {

    $remoteFile = $media->get('field_media_cecc_remote_file');

    $path = $remoteFile->value;

    $fileSize = $media->getSource()->getMetadata($media, 'filesize');

    $element = [
      '#theme' => 'cecc_download_link',
      '#media' => $media,
      '#product' => $product,
      '#product_title' => $product->get('field_cecc_display_title')->value,
      '#product_url' => $path,
      '#link_alt' => $media->getName(),
      '#file_size' => empty($fileSize) ? NULL : format_size($fileSize),
    ];

    return $element;
  }

  /**
   * Gets document media file.
   *
   * @param \Drupal\media\Entity\Media $media
   *   The media item.
   * @param \Drupal\file\Entity\File $file
   *   The file item.
   * @param \Drupal\commerce_product\Entity\Product $product
   *   The parent product.
   *
   * @return array
   *   The render array.
   */
  private function getDocumentElement($media, $file, $product) {
    /** @var \Drupal\commerce_product\Entity\Product $product */
    $product = $media->_referringItem->getEntity();
    $file_size = $file->getSize();
    $url = $file->createFileUrl();

    $element = [
      '#theme' => 'cecc_download_link',
      '#media' => $media,
      '#product' => $product,
      '#product_title' => $product->get('field_cecc_display_title')->value,
      '#product_url' => $url,
      '#link_alt' => $media->getName(),
      '#file_size' => format_size($file_size),
    ];

    return $element;
  }

}
