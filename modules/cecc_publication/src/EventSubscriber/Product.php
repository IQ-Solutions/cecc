<?php

namespace Drupal\cecc_publication\EventSubscriber;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Event\ProductEvent;
use Drupal\commerce_product\Event\ProductEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Product event subscriber.
 */
class Product implements EventSubscriberInterface {

  /**
   * Drupal logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Drupal configfactory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Contstructs a new order event subscriber.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Logger Channel Factory service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config Factory service.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database service.
   */
  public function __construct(
    LoggerChannelFactoryInterface $loggerFactory,
    ConfigFactoryInterface $configFactory,
    Connection $connection
  ) {
    $this->logger = $loggerFactory->get('cecc');
    $this->configFactory = $configFactory;
    $this->connection = $connection;
  }

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      ProductEvents::PRODUCT_UPDATE => [
        'onProductUpdate',
        -100,
      ],
      ProductEvents::PRODUCT_INSERT => [
        'onProductInsert',
        -100,
      ],
      ProductEvents::PRODUCT_PREDELETE => [
        'onProductPredelete',
        -100,
      ],
    ];

    return $events;
  }

  /**
   * Builds and inserts taxonomy index entries for a given commerce_product.
   *
   * The index lists all terms that are related to a given
   * commerce_product entity, and is therefore maintained
   * at the entity level.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $commerce_product
   *   The commerce_product entity.
   */
  private function buildIndex(ProductInterface $commerce_product) {
    $taxonomy_settings = $this->configFactory->get('taxonomy.settings');
    // We maintain a denormalized table of term/commerce_product relationships,
    // Containing only data for current, published commerce_products.
    if (!$taxonomy_settings->get('maintain_index_table')) {
      return;
    }

    $status = $commerce_product->isPublished();
    // We only maintain the taxonomy index for published commerce_products.
    if ($status && $commerce_product->isDefaultRevision()) {
      // Collect a unique list of all the tids from all commerce_product fields.
      $tid_all = [];
      $entity_reference_class = 'Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem';
      $fields = $commerce_product->getFieldDefinitions();

      foreach ($fields as $field) {
        $field_name = $field->getName();
        $class = $field->getItemDefinition()->getClass();
        $is_entity_reference_class = ($class === $entity_reference_class) || is_subclass_of($class, $entity_reference_class);

        if ($is_entity_reference_class && $field->getSetting('target_type') == 'taxonomy_term') {
          foreach ($commerce_product->getTranslationLanguages() as $language) {
            foreach ($commerce_product->getTranslation($language->getId())->$field_name as $item) {
              if (!$item->isEmpty()) {
                $tid_all[$item->target_id] = $item->target_id;
              }
            }
          }
        }
      }

      // Insert index entries for all the commerce_product's terms.
      if (!empty($tid_all)) {
        foreach ($tid_all as $tid) {
          $this->connection->merge('cecc_publication_taxonomy_index')->key([
            'product_id' => $commerce_product->id(),
            'tid' => $tid,
            'status' => $commerce_product->isPublished(),
          ])
            ->fields(['created' => $commerce_product->getCreatedTime()])
            ->execute();
        }
      }
    }
  }

  /**
   * Deletes taxonomy index entries for a given commerce_product.
   *
   * @param \Drupal\Core\Entity\EntityInterface $commerce_product
   *   The commerce_product entity.
   */
  private function deleteIndex(EntityInterface $commerce_product) {
    $taxonomy_settings = $this->configFactory->get('taxonomy.settings');
    if ($taxonomy_settings->get('maintain_index_table')) {
      $this->connection->delete('cecc_publication_taxonomy_index')
        ->condition('product_id', $commerce_product->id())
        ->execute();
    }
  }

  /**
   * Adds to product taxonomy index on insert.
   *
   * @param \Drupal\commerce_product\Event\ProductEvent $event
   *   The commerce product entity.
   */
  public function onProductInsert(ProductEvent $event) {
    $commerce_product = $event->getProduct();
    // Add taxonomy index entries for the commerce_product.
    $this->buildIndex($commerce_product);
  }

  /**
   * Deletes and adds to product taxonomy index on update.
   *
   * @param \Drupal\commerce_product\Event\ProductEvent $event
   *   The commerce product entity.
   */
  public function onProductUpdate(ProductEvent $event) {
    $commerce_product = $event->getProduct();
    // If we're not dealing with the default revision of the commerce_product,
    // Do not make any change to the commerce product taxonomy index.
    if (!$commerce_product->isDefaultRevision()) {
      return;
    }

    $this->deleteIndex($commerce_product);
    $this->buildIndex($commerce_product);
  }

  /**
   * Deletes the taxonomy index item.
   *
   * @param \Drupal\commerce_product\Event\ProductEvent $event
   *   The commerce product entity.
   */
  public function onProductPredelete(ProductEvent $event) {
    $commerce_product = $event->getProduct();
    // Clean up the {commerce_product_taxonomy_index} table.
    // When commerce_products are deleted.
    $this->deleteIndex($commerce_product);
  }

}
