<?php

namespace Drupal\probo\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'ProboAssetsList' block.
 *
 * @Block(
 *  id = "probo_assets_list",
 *  admin_label = @Translation("Probo Assets List"),
 * )
 */
class ProboAssetsList extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['probo_assets_list']['#markup'] = 'Implement ProboAssetsList.';

    return $build;
  }

}
