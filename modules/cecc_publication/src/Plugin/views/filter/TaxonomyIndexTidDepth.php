<?php

namespace Drupal\cecc_publication\Plugin\views\filter;

use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormStateInterface;

/**
 * Filter handler for taxonomy terms with depth.
 *
 * This handler is actually part of the commerce_product table,
 * and has some restrictions,
 * because it uses a subquery to find commerce_products with.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("cecc_publication_taxonomy_index_tid_depth")
 */
class TaxonomyIndexTidDepth extends TaxonomyIndexTid {

  /**
   * {@inheritdoc}
   */
  public function operatorOptions($which = 'title') {
    return [
      'or' => $this->t('Is one of'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['depth'] = ['default' => 0];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildExtraOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildExtraOptionsForm($form, $form_state);

    $form['depth'] = [
      '#type' => 'weight',
      '#title' => $this->t('Depth'),
      '#default_value' => $this->options['depth'],
      '#description' => $this->t('The depth will match commerce_products tagged with terms in the hierarchy. For example, if you have the term "fruit" and a child term "apple", with a depth of 1 (or higher) then filtering for the term "fruit" will get commerce_products that are tagged with "apple" as well as "fruit". If negative, the reverse is true; searching for "apple" will also pick up commerce_products tagged with "fruit" if depth is -1 (or lower).'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // If no filter values are present, then do nothing.
    if (count($this->value) == 0) {
      return;
    }
    elseif (count($this->value) == 1) {
      // Sometimes $this->value is an array with a single element so convert it.
      if (is_array($this->value)) {
        $this->value = current($this->value);
      }
      $operator = '=';
    }
    else {
      $operator = 'IN';
    }

    // The normal use of ensureMyTable() here breaks Views.
    // So instead we trick the filter into using the alias of the base table.
    // See https://www.drupal.org/commerce_product/271833.
    // If a relationship is set, we must use the alias it provides.
    if (!empty($this->relationship)) {
      $this->tableAlias = $this->relationship;
    }
    // If no relationship, then use the alias of the base table.
    else {
      $this->tableAlias = $this->query->ensureTable($this->view->storage->get('base_table'));
    }

    // Now build the subqueries.
    $subquery = Database::getConnection()->select('cecc_publication_taxonomy_index', 'tn');
    $subquery->addField('tn', 'product_id');
    $where = (Database::getConnection()->condition('OR'))->condition('tn.tid', $this->value, $operator);
    $last = "tn";

    if ($this->options['depth'] > 0) {
      $subquery->leftJoin('taxonomy_term__parent', 'th', "th.entity_id = tn.tid");
      $last = "th";
      foreach (range(1, abs($this->options['depth'])) as $count) {
        $subquery->leftJoin('taxonomy_term__parent', "th$count", "$last.parent_target_id = th$count.entity_id");
        $where->condition("th$count.entity_id", $this->value, $operator);
        $last = "th$count";
      }
    }
    elseif ($this->options['depth'] < 0) {
      foreach (range(1, abs($this->options['depth'])) as $count) {
        $field = $count == 1 ? 'tid' : 'entity_id';
        $subquery->leftJoin('taxonomy_term__parent', "th$count", "$last.$field = th$count.parent_target_id");
        $where->condition("th$count.entity_id", $this->value, $operator);
        $last = "th$count";
      }
    }

    $subquery->condition($where);
    $this->query->addWhere($this->options['group'], "$this->tableAlias.$this->realField", $subquery, 'IN');
  }

}
