<?php

namespace Drupal\cecc_api\Plugin\rest\resource;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a publication resource.
 *
 * @RestResource(
 *   id = "publication_resource",
 *   label = @Translation("Commerce product publication resource"),
 *   uri_paths = {
 *     "canonical" = "/catalog_api/publication"
 *   }
 * )
 */
class PublicationResource extends ResourceBase {

  /**
   * EntityType Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeMananger;

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The request object that contains the parameters.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The API config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The field mapping config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $fieldMapping;

  /**
   * Constructs a new object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The request object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user,
    Request $request,
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->request = $request;
    $this->currentUser = $current_user;
    $this->entityTypeMananger = $entity_type_manager;
    $this->fieldMapping = $config_factory->get('cecc_api.publication_field_mapping');
    $this->config = $config_factory->get('cecc_api.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('cecc_api'),
      $container->get('current_user'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  private function mapField($prefix, $field) {
    $fieldName = $prefix . '_' . $field;
    $sendField = $this->fieldMapping->get($fieldName . '_send');
    return $sendField ? $this->fieldMapping->get($prefix . '_' . $field) : FALSE;
  }

  /**
   * Map field values.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface|\Drupal\commerce_product\Entity\ProductVariationInterface $entity
   *   The product entity.
   * @param string $type
   *   The field type.
   * @param string $fieldName
   *   The field name.
   *
   * @return string
   *   The field value.
   */
  private function mapFieldValue($entity, $type, $fieldName) {

    switch ($type) {
      case 'string':
      case 'list_string':
      case 'integer':
        return $entity->get($fieldName)->value;

      case 'datetime':
        return $entity->get($fieldName)->value;

      case 'text_with_summary':
      case 'text_long':
        return $entity->get($fieldName)->value;

      case 'entity_reference':
        $field = $entity->get($fieldName);
        $targetType = $field->getFieldDefinition()->getSetting('target_type');
        $value = '';

        switch ($targetType) {
          case 'taxonomy_term':
            $value = implode(';', $this->getTaxonomy($field));
            break;

          case 'media':
            $value = $this->getMedia($field);
            break;
        }

        return $value;

    }
  }

  /**
   * Responses to entity get request.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The resource response.
   */
  public function get() {
    $response = ['code' => 200];

    $query = $this->entityTypeMananger->getStorage('commerce_product_variation')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'cecc_publication');

    $productIds = $query->execute();

    /** @var \Drupal\commerce_product\Entity\ProductVariation[] $publications */
    $publications = $this->entityTypeMananger->getStorage('commerce_product_variation')
      ->loadMultiple($productIds);

    foreach ($publications as $publication) {
      $pvfFields = $publication->getFields();
      $pubArray = [];

      foreach ($pvfFields as $key => $fieldItemList) {
        $fieldName = $this->mapField('pvf', $key);
        $type = $fieldItemList->getFieldDefinition()->getType();

        if ($fieldName !== FALSE) {
          $pubArray[$fieldName] = $this->mapFieldValue($publication, $type, $key);
        }
      }

      $product = $publication->getProduct();

      if ($product) {
        $pfFields = $product->getFields();

        foreach ($pfFields as $key => $fieldItemList) {
          $fieldName = $this->mapField('pf', $key);
          $type = $fieldItemList->getFieldDefinition()->getType();

          if ($fieldName !== FALSE) {
            $pubArray[$fieldName] = $this->mapFieldValue($product, $type, $key);
          }
        }
      }

      if (!empty($pubArray)) {
        $response['publications'][] = $pubArray;
      }
    }

    $build = [
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    return (new ResourceResponse($response))->addCacheableDependency($build);
  }

  /**
   * Get product taxonomy.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $fieldItemList
   *   The field object.
   *
   * @return array
   *   Array of taxonomy terms.
   */
  private function getTaxonomy(FieldItemListInterface $fieldItemList) {
    $taxonomy = [];

    foreach ($fieldItemList as $field) {
      $taxonomy[] = $field->entity->label();
    }

    return $taxonomy;
  }

  /**
   * Get product taxonomy.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $fieldItemList
   *   The field object.
   *
   * @return array
   *   Array of media urls.
   */
  private function getMedia(FieldItemListInterface $fieldItemList) {
    /** @var \Drupal\media\Entity\Media $media */
    $media = $fieldItemList->entity;

    if (!$media) {
      return '';
    }

    $mediaType = $media->bundle();
    $mediaLinks = [
      'src' => NULL,
    ];

    if (!$fieldItemList->isEmpty()) {
      /** @var \Drupal\file\Entity\File $file */
      $file = $media->get('field_media_' . $mediaType)->entity;
      $fileUri = $file->getFileUri();

      $filePath = file_create_url($fileUri);
      $mediaLinks['src'] = $filePath ?: NULL;
    }

    return $mediaLinks;
  }

  /**
   * Get image style object.
   *
   * @param string $imageStyle
   *   The image style machine name.
   *
   * @return \Drupal\image\Entity\ImageStyle
   *   The image style object.
   */
  private function getImageStyle($imageStyle) {
    return $this->entityTypeMananger->getStorage('image_style')
      ->load($imageStyle);
  }

}
