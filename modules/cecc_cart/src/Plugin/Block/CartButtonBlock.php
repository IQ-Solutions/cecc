<?php

namespace Drupal\cecc_cart\Plugin\Block;

use Drupal\commerce_cart\CartProviderInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a cart button block.
 *
 * @Block(
 *   id = "cecc_commerce_cart_button",
 *   admin_label = @Translation("Cart Button"),
 *   category = @Translation("CEC Catalog")
 * )
 */
class CartButtonBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new CartBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   *   The cart provider.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    CartProviderInterface $cart_provider,
    EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->cartProvider = $cart_provider;
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('commerce_cart.cart_provider'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'item_count_type' => 'total_items',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $form['item_count_type'] = [
      '#title' => $this->t('Item Count Display'),
      '#description' => $this->t('Choose if the count will display the total quantity of items in the cart or the total number of items. Default is total items.'),
      '#type' => 'select',
      '#required' => TRUE,
      '#default_value' => $this->configuration['item_count_type'],
      '#options' => [
        'total_quantity' => $this->t('Total Quantity'),
        'total_items' => $this->t('Total Items'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $this->configuration['item_count_type'] = $form_state->getValue('item_count_type');
  }

  /**
   * Builds the cart block.
   *
   * @return array
   *   A render array.
   */
  public function build() {
    $cartIcon = '/' . drupal_get_path('module', 'commerce') . '/icons/ffffff/cart.png';
    $cartUrl = Url::fromRoute('commerce_cart.page')->toString();
    $count = 0;
    $publicationCount = 0;

    $cachable_metadata = new CacheableMetadata();
    $cachable_metadata->addCacheContexts(['user', 'session']);

    /** @var \Drupal\commerce_order\Entity\OrderInterface[] $carts */
    $carts = $this->cartProvider->getCarts();
    $carts = array_filter($carts, function ($cart) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $cart */
      // There is a chance the cart may have converted from a draft order, but
      // is still in session. Such as just completing check out. So we verify
      // that the cart is still a cart.
      return $cart->hasItems() && $cart->cart->value;
    });

    // @todo make this configurable later.
    if (!empty($carts)) {
      foreach ($carts as $cart) {
        foreach ($cart->getItems() as $order_item) {
          $publicationCount++;
          $count += (int) $order_item->getQuantity();
        }

        $cachable_metadata->addCacheableDependency($cart);
      }
    }

    if ($count > 0) {
      $count = $this->configuration['item_count_type'] == 'total_items'
      ? $publicationCount : $count;
    }

    return [
      '#title' => $this->t('View Cart'),
      '#description' => $this->t('CECC view cart button.'),
      '#theme' => 'cecc_cart_button',
      '#cart_url' => $cartUrl,
      '#item_count' => $count,
      '#cache' => [
        'contexts' => ['cart'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['cart']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $cache_tags = parent::getCacheTags();
    $cart_cache_tags = [];

    /** @var \Drupal\commerce_order\Entity\OrderInterface[] $carts */
    $carts = $this->cartProvider->getCarts();

    foreach ($carts as $cart) {
      // Add tags for all carts regardless items or cart flag.
      $cart_cache_tags = Cache::mergeTags($cart_cache_tags, $cart->getCacheTags());
    }

    return Cache::mergeTags($cache_tags, $cart_cache_tags);
  }

}
