<?php

namespace Drupal\cecc_publication\Plugin\views\argument;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Argument handler for taxonomy terms with depth.
 *
 * This handler is actually part of the commerce_product table,
 * And it has some restrictions,
 * because it uses a subquery to find commerce_products with.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("cecc_publication_taxonomy_index_tid_depth")
 */
class IndexTidDepth extends ArgumentPluginBase implements ContainerFactoryPluginInterface {

  /**
   * This variable store the term storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $termStorage;

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityStorageInterface $termStorage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->termStorage = $termStorage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('taxonomy_term')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['depth'] = ['default' => 0];
    $options['break_phrase'] = ['default' => FALSE];
    $options['use_taxonomy_term_path'] = ['default' => FALSE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['depth'] = [
      '#type' => 'weight',
      '#title' => $this->t('Depth'),
      '#default_value' => $this->options['depth'],
      '#description' => $this->t('The depth will match commerce_products tagged with terms in the hierarchy. For example, if you have the term "fruit" and a child term "apple", with a depth of 1 (or higher) then filtering for the term "fruit" will get commerce_products that are tagged with "apple" as well as "fruit". If negative, the reverse is true; searching for "apple" will also pick up commerce_products tagged with "fruit" if depth is -1 (or lower).'),
    ];

    $form['break_phrase'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow multiple values'),
      '#description' => $this->t('If selected, users can enter multiple values in the form of 1+2+3. Due to the number of JOINs it would require, AND will be treated as OR with this filter.'),
      '#default_value' => !empty($this->options['break_phrase']),
    ];

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * Override defaultActions() to remove summary actions.
   */
  protected function defaultActions($which = NULL) {
    if ($which) {
      if (in_array($which, ['ignore', 'not found', 'empty', 'default'])) {
        return parent::defaultActions($which);
      }
      return;
    }
    $actions = parent::defaultActions();
    unset($actions['summary asc']);
    unset($actions['summary desc']);
    unset($actions['summary asc by count']);
    unset($actions['summary desc by count']);
    return $actions;
  }

  /**
   * Override query() to add conditions with taxomony module.
   */
  public function query($group_by = FALSE) {
    $this->ensureMyTable();

    if (!empty($this->options['break_phrase'])) {
      $break = static::breakString($this->argument);
      if ($break->value === [-1]) {
        return FALSE;
      }

      $operator = (count($break->value) > 1) ? 'IN' : '=';
      $tids = $break->value;
    }
    else {
      $operator = "=";
      $tids = $this->argument;
    }
    // Now build the subqueries.
    $subquery = Database::getConnection()->select('cecc_publication_taxonomy_index', 'tn');
    $subquery->addField('tn', 'product_id');
    $where = (Database::getConnection()->condition('OR'))->condition('tn.tid', $tids, $operator);
    $last = "tn";

    if ($this->options['depth'] > 0) {
      $subquery->leftJoin('taxonomy_term__parent', 'th', "th.entity_id = tn.tid");
      $last = "th";
      foreach (range(1, abs($this->options['depth'])) as $count) {
        $subquery->leftJoin('taxonomy_term__parent', "th$count", "$last.parent_target_id = th$count.entity_id");
        $where->condition("th$count.entity_id", $tids, $operator);
        $last = "th$count";
      }
    }
    elseif ($this->options['depth'] < 0) {
      foreach (range(1, abs($this->options['depth'])) as $count) {
        $field = $count == 1 ? 'tid' : 'entity_id';
        $subquery->leftJoin('taxonomy_term__parent', "th$count", "$last.$field = th$count.parent_target_id");
        $where->condition("th$count.entity_id", $tids, $operator);
        $last = "th$count";
      }
    }

    $subquery->condition($where);
    $this->query->addWhere(0, "$this->tableAlias.$this->realField", $subquery, 'IN');
  }

  /**
   * Override query() to add conditions with taxomony module.
   */
  public function title() {
    $term = $this->termStorage->load($this->argument);
    if (!empty($term)) {
      return $term->getName();
    }
    // TODO review text.
    return $this->t('No name');
  }

}
