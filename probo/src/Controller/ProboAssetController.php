<?php

namespace Drupal\probo\Controller;

use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Component\Render\FormattableMarkup; 

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
    $query->leftJoin('probo_repositories', 'pr', 'pr.rid = pa.rid');
    $query->orderBy('pr.owner', 'ASC');
    $query->orderBy('pr.repository', 'ASC');    
    $assets = $query->execute()->fetchAllAssoc('aid');

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
      $download = Link::fromTextAndUrl(t('Download'), Url::fromRoute('probo.admin_config_system_probo_asset_download', ['aid' => $asset->aid]))->toString();

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
          'data' => new FormattableMarkup($link . ' | ' . $download, []),
          'style' => 'text-align: center; font-family: courier, monospace;',
        ],
      ];
      $rows[] = $row;
    }

    $footer = [];
    $add_new = '<p align="right">' . Link::fromTextAndUrl(t('Add New Asset'), Url::fromRoute('probo.admin_config_system_probo_asset_add'))->toString() . '</p>';

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
    $client = \Drupal::httpClient();
    $config = $this->config('probo.probosettings');

    // Get the filename, owner/organization and repository for deleting the asset.
    $query = \Drupal::database()->select('probo_assets', 'pa');
    $query->addField('pa', 'filename');
    $query->addField('pr', 'owner');
    $query->addField('pr', 'repository');
    $query->orderBy('pr.owner', 'ASC');
    $query->orderBy('pr.repository', 'ASC');
    $query->join('probo_repositories', 'pr', 'pr.rid = pa.rid');
    $query->condition('pa.aid', $aid);
    $assets = $query->execute()->fetchAllAssoc('rid');
    $assets = array_pop($assets);

    $response = $client->request('DELETE', $config->get('asset_manager_url_port') . '/buckets/' . $assets->owner . '-' . $assets->repository . '/assets/ ' . $assets->filename);
    $buffer = $response->getBody();

    drupal_set_message($buffer);

    // Remove the reference from the table.
    $query = \Drupal::database()->delete('probo_assets')
      ->condition('aid', $aid)
      ->execute();

    return new RedirectResponse(\Drupal::url('probo.admin_config_system_probo_assets'));
  }

  public function download_asset($aid): RedirectResponse {
    $client = \Drupal::httpClient();
    $config = $this->config('probo.probosettings');

    // Get the filename, owner/organization and repository for deleting the asset.
    $query = \Drupal::database()->select('probo_assets', 'pa');
    $query->addField('pa', 'filename');
    $query->addField('pr', 'owner');
    $query->addField('pr', 'repository');
    $query->orderBy('pr.owner', 'ASC');
    $query->orderBy('pr.repository', 'ASC');
    $query->join('probo_repositories', 'pr', 'pr.rid = pa.rid');
    $query->condition('pa.aid', $aid);
    $assets = $query->execute()->fetchAllAssoc('rid');
    $assets = array_pop($assets);

    $url = $config->get('asset_manager_url_port') . '/asset/' . $assets->owner . '-' . $assets->repository . '/' . $assets->filename;
    
    return new TrustedRedirectResponse($url);
  }

}
