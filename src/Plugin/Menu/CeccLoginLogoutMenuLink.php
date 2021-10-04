<?php

namespace Drupal\cecc\Plugin\Menu;

use Drupal\user\Plugin\Menu\LoginLogoutMenuLink;

/**
 * A create account menu link.
 */
class CeccLoginLogoutMenuLink extends LoginLogoutMenuLink {

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    if ($this->currentUser->isAuthenticated()) {
      return $this->t('Log Out');
    }
    else {
      return $this->t('Log In');
    }
  }

}
