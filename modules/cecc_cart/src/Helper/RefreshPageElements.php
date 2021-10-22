<?php

namespace Drupal\cecc_cart\Helper;

use Drupal\commerce_cart\CartProviderInterface;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\UpdateBuildIdCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Messenger\MessengerTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\cecc_cart\Ajax\PopoverCommand;

/**
 * Provides methods that would help in refreshing certain page elements.
 */
class RefreshPageElements {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * If request has errors. Defaults to FALSE.
   *
   * @var bool
   */
  private $hasErrors = FALSE;

  /**
   * Ajax response.
   *
   * @var \Drupal\Core\Ajax\AjaxResponse
   */
  protected $response;

  /**
   * Theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * Renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The entity that was added to the cart.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariationInterface
   */
  protected $purchasedEntity;

  /**
   * The entity that was added to the cart.
   *
   * @var \Drupal\commerce_order\Entity\OrderItemInterface
   */
  protected $orderItem;

  /**
   * The form state.
   *
   * @var \Drupal\Core\Form\FormStateInterface
   */
  protected $formState;

  /**
   * Constructs a new RefreshPageElementsHelper object.
   *
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(
    ThemeManagerInterface $theme_manager,
    EntityTypeManagerInterface $entity_type_manager,
    BlockManagerInterface $block_manager,
    RendererInterface $renderer,
    CartProviderInterface $cart_provider) {
    $this->themeManager = $theme_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->blockManager = $block_manager;
    $this->renderer = $renderer;
    $this->response = new AjaxResponse();
    $this->cartProvider = $cart_provider;
  }

  /**
   * Creates instance of RefreshPageElementsHelper class.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('theme.manager'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.block'),
      $container->get('renderer'),
      $container->get('commerce_cart.cart_provider')
    );
  }

  /**
   * Refreshes status messages.
   *
   * @return $this
   */
  public function updateStatusMessages() {

    $content = $this->generatePopoverContent();
    $type = $this->hasErrors ? 'danger' : 'success';

    $this->response->addCommand(new PopoverCommand('', $type, $content, []));

    return $this;
  }

  /**
   * Returns cart block.
   *
   * @return \Drupal\Core\Block\BlockPluginInterface
   *   The cart block.
   */
  protected function getCartBlock() {
    /** @var \Drupal\Core\Block\BlockPluginInterface $block */
    $block = $this->blockManager->createInstance('po_commerce_cart_button', []);

    return $block;
  }

  /**
   * Updates content inside cart block.
   *
   * @return $this
   */
  public function updateCart() {
    /** @var \Drupal\Core\Block\BlockPluginInterface $block */
    $block = $this->getCartBlock();

    $this->response->addCommand(new ReplaceCommand('.block-po-commerce-cart-button a', $block->build()));

    return $this;
  }

  /**
   * Updates the form build id.
   *
   * @param array $form
   *   Drupal form.
   *
   * @return $this
   */
  public function updateFormBuildId(array $form) {
    // If the form build ID has changed, issue an Ajax command to update it.
    if (isset($form['#build_id_old']) && $form['#build_id_old'] !== $form['#build_id']) {
      $this->response->addCommand(new UpdateBuildIdCommand($form['#build_id_old'], $form['#build_id']));
    }

    return $this;
  }

  public function getPurchasedEntity(array $form, FormStateInterface $form_state) {
    $this->orderItem = $form_state->getFormObject()->getEntity();

    $this->purchasedEntity = $this->orderItem ? $this->orderItem->getPurchasedEntity() : NULL;
    $this->formState = $form_state;
  }

  /**
   * Updates page elements.
   *
   * @param array $form
   *   Drupal form.
   *
   * @return $this
   */
  public function updatePageElements(array $form, FormStateInterface $form_state, $error_container = NULL) {
    $this->getPurchasedEntity($form, $form_state);

    return $this->updateFormBuildId($form)
      ->updateStatusMessages()
      ->updateCart();
  }

  /**
   * Returns the ajax response.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response.
   */
  public function getResponse() {
    return $this->response;
  }

  /**
   * Displays the added product in the modal.
   */
  private function getErrorMessages() {
    $messages = $this->messenger()->messagesByType(Messenger::TYPE_ERROR);
    $messageList = [];

    foreach ($messages as $message) {
      if (strpos($message->__toString(), 'error has been found') === FALSE) {
        $messageList[] = $message;
      }
    }

    $this->messenger()->deleteByType(Messenger::TYPE_ERROR);

    $this->hasErrors = !empty($messageList);

    return $messageList;
  }

  /**
   * Displays gets status messages and generates popover content.
   */
  private function generatePopoverContent() {
    $content = NULL;
    $messageList = $this->getErrorMessages();

    if (empty($messageList)) {
      $quantity = $this->orderItem ? $this->orderItem->getQuantity() : 0;

      if ($quantity == 0) {
        $content = $this->t('Please go to @cart_link to remove this item.', [
          '@cart_link' => Link::createFromRoute('your cart', 'commerce_cart.page')->toString(),
        ]);
      }
      else {
        $content = $this->t('@quantity of %label is now in @cart_link.', [
          '@quantity' => $quantity,
          '%label' => $this->orderItem->getTitle(),
          '@cart_link' => Link::createFromRoute('your cart', 'commerce_cart.page')->toString(),
        ]);
      }
    }
    else {
      $content = implode('', $messageList);
    }

    $build = [];

    if (!empty($content)) {
      $build = [
        '#theme' => 'cec_tippy',
        '#content' => $content,
        '#type' => $this->hasErrors ? 'danger' : 'success',
        '#cache' => [
          'max-age' => 0,
        ],
      ];
    }

    return $build;
  }

  /**
   * Displays the added product in the modal.
   *
   * @param \Drupal\Component\Render\MarkupInterface[] $errorMessages
   *   Array of Markup objects.
   */
  private function displayCartModalTheme(array $errorMessages = NULL) {
    $messageList = [];
    foreach ($errorMessages as $errorMessage) {
      if (strpos($errorMessage->__toString(), 'error has been found') === FALSE) {
        $messageList[] = $errorMessage->__toString();
      }
    }

    /** @var \Drupal\commerce_order\Entity\OrderInterface[] $carts */
    $carts = $this->cartProvider->getCarts();
    $carts = array_filter($carts, function ($cart) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $cart */
      // There is a chance the cart may have converted from a draft order, but
      // is still in session. Such as just completing check out. So we verify
      // that the cart is still a cart.
      return $cart->hasItems() && $cart->cart->value;
    });

    $viewBuilder = $this->entityTypeManager->getViewBuilder('commerce_order_item');

    $orderItemArray = [];

    // @todo make this configurable later.
    if (!empty($carts)) {
      foreach ($carts as $cart) {
        foreach ($cart->getItems() as $order_item) {
          $orderItemArray[] = $viewBuilder->view($order_item, 'ajax_cart');
        }
      }
    }

    $build = [
      '#theme' => 'po_show_cart_modal',
      '#order_items' => $orderItemArray,
      '#purchased_entity' => $this->purchasedEntity->id(),
      '#messageList' => $messageList,
      '#cart_url' => Url::fromRoute('commerce_cart.page')->toString(),
    ];

    return $build;
  }

}
