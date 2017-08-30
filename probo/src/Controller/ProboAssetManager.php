<?php

namespace Drupal\probo\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class ProboAssetManager.
 */
class ProboAssetManager extends ControllerBase {

  /**
   * Asset_manager.
   *
   * @return string
   *   Return Hello string.
   */
  public function asset_manager() {
    return [
      '#type' => 'markup',
      '#markup' => $this->t('Implement method: asset_manager')
    ];
  }

}
