<?php

namespace Drupal\cecc_remote_file\Plugin\media\Source;

use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\media\MediaTypeInterface;

/**
 * External file entity media source.
 *
 * @see \Drupal\file\FileInterface
 *
 * @MediaSource(
 *   id = "cecc_remote_file",
 *   label = @Translation("Remote File"),
 *   description = @Translation("Use remote files."),
 *   allowed_field_types = {"string_long"},
 *   default_thumbnail_filename = "generic.png"
 * )
 */
class RemoteFile extends MediaSourceBase {

  /**
   * Key for "Name" metadata attribute.
   *
   * @var string
   */
  const METADATA_ATTRIBUTE_NAME = 'name';

  /**
   * Key for "MIME type" metadata attribute.
   *
   * @var string
   */
  const METADATA_ATTRIBUTE_MIME = 'mimetype';

  /**
   * Key for "File size" metadata attribute.
   *
   * @var string
   */
  const METADATA_ATTRIBUTE_SIZE = 'filesize';

  /**
   * {@inheritDoc}
   */
  public function getMetadataAttributes() {
    return [
      static::METADATA_ATTRIBUTE_NAME => $this->t('Name'),
      static::METADATA_ATTRIBUTE_MIME => $this->t('MIME type'),
      static::METADATA_ATTRIBUTE_SIZE => $this->t('File size'),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getMetadata(MediaInterface $media, $attribute_name) {
    $remote_field = $media->get($this->configuration['source_field']);
    $file = new \SplFileObject($remote_field->value);

    // If the source field is not required, it may be empty.
    if (!$remote_field) {
      return parent::getMetadata($media, $attribute_name);
    }

    switch ($attribute_name) {
      case static::METADATA_ATTRIBUTE_NAME:
      case 'default_name':
        return $file->getFilename();

      case static::METADATA_ATTRIBUTE_MIME:
        return mime_content_type($remote_field->value);

      case static::METADATA_ATTRIBUTE_SIZE:
        return $this->getRemoteFilesize($remote_field->value);

      case 'thumbnail_uri':
        return $this->getThumbnail($remote_field->value) ?: parent::getMetadata($media, $attribute_name);

      default:
        return parent::getMetadata($media, $attribute_name);
    }
  }

  /**
   * Gets the thumbnail image URI based on a file entity.
   *
   * @param string $file
   *   A file entity.
   *
   * @return string
   *   File URI of the thumbnail image or NULL if there is no specific icon.
   */
  protected function getThumbnail(string $file) {
    $icon_base = $this->configFactory->get('media.settings')->get('icon_base_uri');

    if (!empty($file)) {
      // We try to automatically use the most specific icon present in the
      // $icon_base directory, based on the MIME type. For instance, if an
      // icon file named "pdf.png" is present, it will be used if the file
      // matches this MIME type.
      $mimetype = mime_content_type($file);
      $mimetype = explode('/', $mimetype);

      $icon_names = [
        $mimetype[0] . '--' . $mimetype[1],
        $mimetype[1],
        $mimetype[0],
      ];

      foreach ($icon_names as $icon_name) {
        $thumbnail = $icon_base . '/' . $icon_name . '.png';
        if (is_file($thumbnail)) {
          return $thumbnail;
        }
      }
    }
    else {
      $thumbnail = $icon_base . '/pdf.png';
      if (is_file($thumbnail)) {
        return $thumbnail;
      }
    }

    return NULL;
  }

  /**
   * Gets file size for remote files from file header.
   *
   * @param string $url
   *   The remote file URL.
   * @param bool $formatSize
   *   Should the file size be formatted. Default true.
   * @param bool $useHead
   *   Get the file size from the file header. Default true.
   *
   * @return int
   *   Return the file size in bytes
   */
  private function getRemoteFilesize($url, $formatSize = TRUE, $useHead = TRUE) {
    if (FALSE !== $useHead) {
      stream_context_set_default([
        'http' => [
          'method' => 'HEAD',
        ],
      ]);
    }

    $head = array_change_key_case(get_headers($url, 1));
    $clen = isset($head['content-length']) ? $head['content-length'] : 0;

    if (!$clen) {
      return -1;
    }

    return $clen;
  }

  /**
   * {@inheritdoc}
   */
  public function createSourceField(MediaTypeInterface $type) {
    return parent::createSourceField($type)->set('settings', ['file_extensions' => 'txt doc docx pdf']);
  }

}
