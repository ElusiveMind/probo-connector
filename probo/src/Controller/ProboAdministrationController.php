<?php

namespace Drupal\probo\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class ProboAdministrationController.
 */
class ProboAdministrationController extends ControllerBase {

  /**
   * Probo_settings.
   *
   * @return string
   *   Return Hello string.
   */
  public function probo_settings() {
    return [
      '#type' => 'markup',
      '#markup' => $this->t('Implement method: probo_settings')
    ];
  }

}
