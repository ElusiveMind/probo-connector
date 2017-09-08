<?php

namespace Drupal\probo\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Provides a 'ProboActiveBuildsBlock' block.
 *
 * @Block(
 *  id = "probo_active_builds_block",
 *  admin_label = @Translation("Probo Active Builds Block"),
 * )
 */
class ProboActiveBuildsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['probo_active_builds_block']['#markup'] = 'Implement ProboActiveBuildsBlock.';

    return $build;
  }

}
