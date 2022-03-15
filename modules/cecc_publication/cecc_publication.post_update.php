<?php

use Drupal\commerce_product\Entity\Product;

/**
 * Saves commerce products to trigger taxonomy table updates.
 */
function cecc_publication_post_update_commerce_taxonomy() {
  $query = \Drupal::entityTypeManager()->getStorage('commerce_product')->getQuery()
    ->condition('status', 1);
  $product_ids = $query->execute();

  foreach ($product_ids as $product_id) {
    $commerce_product = Product::load($product_id);
    $commerce_product->save();
  }
}
