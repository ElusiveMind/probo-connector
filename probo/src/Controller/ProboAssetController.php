<?php

namespace Drupal\probo\Controller;

use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class ProboAssetController.
 */
class ProboAssetController extends ControllerBase {

  /**
   * List_assets.
   *
   * @return string
   *   Return Hello string.
   */
  public function list_assets(): array {
    $query = \Drupal::database()->select('probo_assets', 'pa');
    $query->fields('pa', ['aid', 'rid', 'filename']);
    $query->addField('pr', 'owner');
    $query->addField('pr', 'repository');
    $query->orderBy('pr.owner', 'ASC');
    $query->orderBy('pr.repository', 'ASC');
    $query->join('probo_repositories', 'pr', 'pr.rid = pa.rid');
    $assets = $query->execute()->fetchAllAssoc('rid');

    $header = [
      [
        'data' => $this->t('Owner/Repository'),
      ],
      [
        'data' => $this->t('Filename'),
      ],
      [
        'data' => $this->t('Actions'),
        'style' => 'text-align: center',
      ],
    ];

    $rows = [];
    foreach ($assets as $asset) {
      $link = Link::fromTextAndUrl(t('Delete'), Url::fromRoute('probo.admin_config_system_probo_asset_delete', ['aid' => $asset->aid]))->toString();

      $row = [
        [
          'data' => $asset->owner . '-' . $asset->repository,
          'style' => 'font-family: courier, monospace;',
        ],
        [
          'data' => $asset->filename,
          'style' => 'font-family: courier, monospace;',
        ],
        [
          'data' => $link,
          'style' => 'text-align: center; font-family: courier, monospace;',
        ],
      ];
      $rows[] = $row;
    }

    $footer = [];
    $add_new = Link::fromTextAndUrl(t('Add New Asset'), Url::fromRoute('probo.admin_config_system_probo_asset_add'))->toString();
  
    return [
      '#prefix' => $add_new,
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#footer' => $footer,
      '#empty' => 'THERE ARE NO ASSETS UPLOADED FOR ANY REPOSITORY.',
    ];
  }

  public function delete_asset($aid): RedirectResponse {
    
    return new RedirectResponse(\Drupal::url('probo.admin_config_system_probo_assets'));
  }

  public function download_asset($aid): void {

  }

}
