<?php

namespace Drupal\probo_connector\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'ProboRecentClosedBuilds' block.
 *
 * @Block(
 *  id = "probo_recent_closed_builds",
 *  admin_label = @Translation("Probo Recent Closed Builds"),
 * )
 */
class ProboRecentClosedBuilds extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['probo_recent_closed_builds']['#markup'] = 'Implement ProboRecentClosedBuilds.';

    return $build;
  }

}
