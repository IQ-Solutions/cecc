<?php

namespace Drupal\po_api\Plugin\rest\resource;

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
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->request = $request;
    $this->currentUser = $current_user;
    $this->entityTypeMananger = $entity_type_manager;
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
      $container->get('logger.factory')->get('po_api'),
      $container->get('current_user'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('entity_type.manager')
    );
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
      ->condition('type', 'publication');

    $productIds = $query->execute();

    /** @var \Drupal\commerce_product\Entity\ProductVariation[] $publications */
    $publications = $this->entityTypeMananger->getStorage('commerce_product_variation')
      ->loadMultiple($productIds);

    foreach ($publications as $publication) {
      $product = $publication->getProduct();
      $categories = $this->getProductCategories($product->get('field_product_category'));

      $pdfDownload = $product->get('field_pdf_link');

      $pdfDownloadLink = '';
      $mainImageUrl = $this->getProductCoverImage($product->get('field_cover'));

      if (!$pdfDownload->isEmpty()) {
        $pdfDownloadLink = $pdfDownload->entity
          ->get('field_media_remote_file')->value;
      }

      $pubArray = [
        'title' => $product->getTitle(),
        'sku' => $publication->get('sku')->value,
        'warehouse_item_id' => $publication->get('field_warehouse_item_id')->value,
        'language' => ucwords($product->get('field_po_lanuage')->value),
        'website' => $product->get('field_website')->value,
        'description' => $product->body->value,
        'product_category' => $categories,
        'pdf_download_url' => $pdfDownloadLink,
        'main_image_url' => $mainImageUrl,
        'alernate_language_link' => $product->get('field_spanish_version')->value,
      ];

      $response['publications'][] = $pubArray;
    }

    $build = [
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    return (new ResourceResponse($response))->addCacheableDependency($build);
  }

  private function getProductCategories(FieldItemListInterface $field) {
    $categories = [];

    foreach ($field as $category) {
      $categories[] = $category->entity->label();
    }

    return $categories;
  }

  private function getProductCoverImage(FieldItemListInterface $field) {
    $mainImage = [];
    $media = $field->entity;

    if (!$field->isEmpty()) {
      /** @var \Drupal\file\Entity\File $file */
      $file = $media->get('field_media_image')->entity;
      $fileUri = $file->getFileUri();

      $mainImagePath = file_create_url($fileUri);
      $thumbnailImagePath = $this->entityTypeMananger->getStorage('image_style')
        ->load('list_item')->buildUrl($fileUri);
      $detailImagePath = $this->entityTypeMananger->getStorage('image_style')
        ->load('publication_detail_cover')->buildUrl($fileUri);
      $mainImage['src'] = $mainImagePath ?: NULL;
      $mainImage['thumbnail'] = $thumbnailImagePath ?: NULL;
      $mainImage['detail'] = $detailImagePath ?: NULL;
    }

    return $mainImage;
  }

}
