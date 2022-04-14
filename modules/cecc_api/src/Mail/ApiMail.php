<?php

namespace Drupal\cecc_api\Mail;

use Drupal\commerce\MailHandlerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * API Mail service.
 */
class ApiMail {

  use StringTranslationTrait;

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
   * @param \Drupal\commerce\MailHandlerInterface $mail_handler
   *   The mail handler.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(
    MailHandlerInterface $mail_handler,
    ConfigFactoryInterface $config_factory) {
    $this->mailHandler = $mail_handler;
    $this->configFactory = $config_factory;
  }

  /**
   * Sends the order receipt email.
   *
   * @param array $params
   *   Mail parameter array.
   *
   * @return bool
   *   TRUE if the email was sent successfully, FALSE otherwise.
   */
  public function send(array $params) {
    $siteConfig = $this->configFactory->get('system.site');
    $ceccConfig = $this->configFactory->get('cecc.settings');
    $ceccApiConfig = $this->configFactory->get('cecc_api.settings');
    $siteMail = empty($ceccConfig->get('email_from')) ? $siteConfig->get('mail') :
    $ceccConfig->get('email_from');
    $siteName = empty($ceccConfig->get('email_from_name')) ? $siteConfig->get('name') :
      $ceccConfig->get('email_from_name');
    $to = $ceccApiConfig->get('api_notifications');

    $subject = $this->t('CECC API Notification [@site_name]: @subject', [
      '@subject' => $params['subject'],
      '@site_name' => $siteName,
    ]);

    $body = [];

    $body['message'] = [
      '#type' => 'container',
      '#title' => $this->t('CECC API Notification'),
    ];

    $params = [
      'id' => 'cecc_api_notification',
      'from' => $siteMail,
      'langcode' => 'en',
    ];

    return $this->mailHandler->sendMail($to, $subject, $body, $params);
  }

}
