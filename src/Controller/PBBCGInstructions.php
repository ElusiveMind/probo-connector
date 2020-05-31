<?php

namespace Drupal\probo\Controller;

use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Component\Render\FormattableMarkup; 

/**
 * Class PBBCGInstructions.
 */
class PBBCGInstructions extends ControllerBase {

  /**
   * instructions.
   *
   * @return array
   *   A render array that contains our instructions for using the Probo Bitbucket
   *   configuration tool.
   */
  public function instructions() {
    return [
      '#theme' => 'probo_bitbucket_instructions',
    ];
  }
}