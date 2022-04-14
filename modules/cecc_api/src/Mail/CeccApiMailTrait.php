<?php

namespace Drupal\cecc_api\Mail;

/**
 * CECC API Mail trait.
 */
trait CeccApiMailTrait {

  /**
   * The API Notification service.
   *
   * @var \Drupal\cecc_api\Mail\ApiMail
   */
  protected $apiMail;

  /**
   * Sends mail for the CECC API.
   *
   * @param array $params
   *   The mail params.
   *
   * @return bool
   *   TRUE if the email was sent successfully, FALSE otherwise.
   */
  protected function sendMail(array $params) {
    /** @var \Drupal\cecc_api\Mail\ApiMail $apiMail */
    $apiMail = \Drupal::service('cecc_api.api_notification');

    return $apiMail->send($params);
  }

  /**
   * Gets the string translation service.
   *
   * @return \Drupal\Core\StringTranslation\TranslationInterface
   *   The string translation service.
   */
  protected function getApiMail() {
    if (!$this->apiMail) {
      $this->apiMail = \Drupal::service('cecc_api.api_notification');
    }

    return $this->apiMail;
  }

  /**
   * Sets the service to use.
   *
   * @param \Drupal\cecc_api\Mail\ApiMail $apiMail
   *   The api mail service service.
   *
   * @return $this
   */
  public function setApiMail(ApiMail $apiMail) {
    $this->apiMail = $apiMail;

    return $this;
  }

}
