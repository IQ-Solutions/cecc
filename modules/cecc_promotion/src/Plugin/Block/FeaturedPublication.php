<?php

namespace Drupal\cecc_promotion\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block for the featured publication content type.
 *
 * @Block(
 *   id = "cecc_featured_publication_block",
 *   admin_label = @Translation("Featured Publication"),
 * )
 */
class FeaturedPublication extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity tye manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Featured content block constructor.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param array $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity tye manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function build() {

    $promotion = $this->getPromotion();

    if ($promotion) {
      $promotionImage = NULL;

      if (!$promotion->get('field_featured_image')->isEmpty()) {
        /** @var \Drupal\file\Entity\File $file */
        $file = $promotion->get('field_featured_image')->entity
          ->get('field_media_image')->entity;
        $fileUri = $file->getFileUri();
        $promotionImage = $this->entityTypeManager->getStorage('image_style')
          ->load('publication_detail_cover')->buildUrl($fileUri);
      }

      return [
        '#theme' => 'cecc_featured_promotion',
        '#promotion' => $promotion,
        '#promotion_title' => $promotion->title->value,
        '#promotion_link' => $promotion->toUrl(),
        '#promotion_image' => $promotionImage,
        '#promotion_text' => $promotion->get('body')[0]->value,
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }

  /**
   * {@inheritDoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $promotion = $this->getPromotion();

    $form['selected_promotion'] = [
      '#title' => $this->t('Selected Promotion'),
      '#type' => 'entity_autocomplete',
      '#required' => TRUE,
      '#target_type' => 'node',
      '#default_value' => $promotion,
      '$selection_settings' => [
        'target_bundles' => [
          'promotion',
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $this->configuration['selected_promotion'] = $form_state->getValue('selected_promotion');
  }

  /**
   * Gets the selected promotion.
   *
   * @return \Drupal\node\Entity\Node
   *   The promotion entity.
   */
  private function getPromotion() {
    $config = $this->getConfiguration();
    $selectedPromotion = isset($config['selected_promotion']) ?
      $config['selected_promotion'] : NULL;
    $promotion = NULL;

    if ($selectedPromotion) {
      $id = isset($selectedPromotion['target_id']) ?
        $selectedPromotion['target_id'] : $selectedPromotion;

      $promotion = $id ? $this->entityTypeManager->getStorage('node')->load($id) :
        NULL;
    }

    return $promotion;
  }

}
