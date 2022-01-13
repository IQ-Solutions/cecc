<?php

namespace Drupal\cecc\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;

/**
 * Provides a cart button block.
 *
 * @Block(
 *   id = "cecc_browse_publications",
 *   admin_label = @Translation("Browse Publications Button"),
 *   category = @Translation("CEC Catalog")
 * )
 */
class BrowsePublications extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'label_display' => FALSE,
    ];
  }

  /**
   * Builds the cart block.
   *
   * @return array
   *   A render array.
   */
  public function build() {
    $viewUrl = Url::fromRoute('view.cecc_publications.browse_all')->toString();

    return [
      '#description' => $this->t('Browse all publications'),
      '#theme' => 'cecc_browse_publications_button',
      '#view_url' => $viewUrl,
    ];
  }

}
