<?php

namespace Drupal\cecc_restocked\Mail;

use Drupal\commerce\MailHandlerInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderTotalSummaryInterface;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\Entity\User;

class RestockMail {

  use StringTranslationTrait;

  /**
   * Data storage query.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The mail handler.
   *
   * @var \Drupal\commerce\MailHandlerInterface
   */
  protected $mailHandler;

  /**
   * Drupal config service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * Constructs a new OrderReceiptMail object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce\MailHandlerInterface $mail_handler
   *   The mail handler.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MailHandlerInterface $mail_handler,
    ConfigFactoryInterface $config_factory) {
    $this->mailHandler = $mail_handler;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Sends the order receipt email.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $product
   *   The product.
   * @param \Drupal\user\Entity\User $user
   *   The user requesting restock notification.
   *
   * @return bool
   *   TRUE if the email was sent successfully, FALSE otherwise.
   */
  public function send(ProductInterface $product, User $user) {
    $siteConfig = $this->configFactory->get('system.site');
    $siteMail = $siteConfig->get('mail');
    $siteName = $siteConfig->get('name');
    $to = $user->getEmail();

    $subject = $this->t('Restock Notification for @product_title', [
      '@product_title' => $product->getTitle(),
    ]);

    $body = [
      '#theme' => 'cecc_restocked_notification',
      '#product' => $product,
      '#user' => $user,
      '#site_name' => $siteName,
      '#site_mail' => $siteMail,
    ];

    $params = [
      'id' => 'restock_notification',
      'from' => $siteMail,
      'product' => $product,
    ];

    if ($user->isActive()) {
      $params['langcode'] = $user->getPreferredLangcode();
    }
    else {
      return FALSE;
    }

    return $this->mailHandler->sendMail($to, $subject, $body, $params);
  }

}
