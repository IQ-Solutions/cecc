<?php

namespace Drupal\cecc_publication\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\PrerenderList;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\taxonomy\VocabularyStorageInterface;

/**
 * Field handler to display all taxonomy terms of a commerce_product.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("cecc_publication_taxonomy_index_tid")
 */
class TaxonomyIndexTid extends PrerenderList {

  /**
   * The vocabulary storage.
   *
   * @var \Drupal\taxonomy\VocabularyStorageInterface
   */
  protected $vocabularyStorage;

  /**
   * Constructs a TaxonomyIndexTid object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\taxonomy\VocabularyStorageInterface $vocabulary_storage
   *   The vocabulary storage.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, VocabularyStorageInterface $vocabulary_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->vocabularyStorage = $vocabulary_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('taxonomy_vocabulary')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    // @todo: Wouldn't it be possible to use $this->base_table and no if here?
    if ($view->storage->get('base_table') == 'commerce_product_field_revision') {
      $this->additional_fields['product_id'] = ['table' => 'commerce_product_field_revision', 'field' => 'product_id'];
    }
    else {
      $this->additional_fields['product_id'] = ['table' => 'commerce_product_field_data', 'field' => 'product_id'];
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['link_to_taxonomy'] = ['default' => TRUE];
    $options['limit'] = ['default' => FALSE];
    $options['vids'] = ['default' => []];

    return $options;
  }

  /**
   * Provide "link to term" option.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['link_to_taxonomy'] = [
      '#title' => $this->t('Link this field to its term page'),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->options['link_to_taxonomy']),
    ];

    $form['limit'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Limit terms by vocabulary'),
      '#default_value' => $this->options['limit'],
    ];

    $options = [];
    $vocabularies = $this->vocabularyStorage->loadMultiple();
    foreach ($vocabularies as $voc) {
      $options[$voc->id()] = $voc->label();
    }

    $form['vids'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Vocabularies'),
      '#options' => $options,
      '#default_value' => $this->options['vids'],
      '#states' => [
        'visible' => [
          ':input[name="options[limit]"]' => ['checked' => TRUE],
        ],
      ],

    ];

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * Add this term to the query.
   */
  public function query() {
    $this->addAdditionalFields();
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values) {
    $vocabularies = $this->vocabularyStorage->loadMultiple();
    $this->field_alias = $this->aliases['product_id'];
    $product_ids = [];
    foreach ($values as $result) {
      if (!empty($result->{$this->aliases['product_id']})) {
        $product_ids[] = $result->{$this->aliases['product_id']};
      }
    }

    if ($product_ids) {
      $vocabs = array_filter($this->options['vids']);
      if (empty($this->options['limit'])) {
        $vocabs = [];
      }
      $result = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->getNodeTerms($product_ids, $vocabs);

      foreach ($result as $commerce_product_product_id => $data) {
        foreach ($data as $tid => $term) {
          $this->items[$commerce_product_product_id][$tid]['name'] = \Drupal::service('entity.repository')->getTranslationFromContext($term)->label();
          $this->items[$commerce_product_product_id][$tid]['tid'] = $tid;
          $this->items[$commerce_product_product_id][$tid]['vocabulary_vid'] = $term->bundle();
          $this->items[$commerce_product_product_id][$tid]['vocabulary'] = $vocabularies[$term->bundle()]->label();

          if (!empty($this->options['link_to_taxonomy'])) {
            $this->items[$commerce_product_product_id][$tid]['make_link'] = TRUE;
            $this->items[$commerce_product_product_id][$tid]['path'] = 'taxonomy/term/' . $tid;
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render_item($count, $item) {
    return $item['name'];
  }

  /**
   * {@inheritdoc}
   */
  protected function documentSelfTokens(&$tokens) {
    $tokens['{{ ' . $this->options['id'] . '__tid' . ' }}'] = $this->t('The taxonomy term ID for the term.');
    $tokens['{{ ' . $this->options['id'] . '__name' . ' }}'] = $this->t('The taxonomy term name for the term.');
    $tokens['{{ ' . $this->options['id'] . '__vocabulary_vid' . ' }}'] = $this->t('The machine name for the vocabulary the term belongs to.');
    $tokens['{{ ' . $this->options['id'] . '__vocabulary' . ' }}'] = $this->t('The name for the vocabulary the term belongs to.');
  }

  /**
   * {@inheritdoc}
   */
  protected function addSelfTokens(&$tokens, $item) {
    foreach (['tid', 'name', 'vocabulary_vid', 'vocabulary'] as $token) {
      $tokens['{{ ' . $this->options['id'] . '__' . $token . ' }}'] = isset($item[$token]) ? $item[$token] : '';
    }
  }

}
