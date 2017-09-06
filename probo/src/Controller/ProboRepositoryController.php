<?php

namespace Drupal\probo\Controller;

use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\probo\Controller\ProboAssetController;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class ProboRepositoryController.
 */
class ProboRepositoryController extends ControllerBase {

  /**
   * display_repositories.
   *
   * @return array
   *   Return render array of a table of elements that make up the list
   *   of available repositories or an empty list.
   */
   public function display_repositories(): array {
    $query = \Drupal::database()->select('probo_repositories', 'pr')
      ->fields('pr', ['rid', 'owner', 'repository', 'token'])
      ->condition('active', TRUE)
      ->orderBy('owner', 'ASC')
      ->orderBy('repository', 'ASC');
    $repositories = $query->execute()->fetchAllAssoc('rid');

    $header = [
      [
        'data' => $this->t('Owner'),
      ],
      [
        'data' => $this->t('Repository'),
      ],
      [
        'data' => $this->t('Token'),
        'style' => 'text-align: center',
      ],
      [
        'data' => $this->t('Actions'),
        'style' => 'text-align: center',
      ],
    ];

    $rows = [];
    foreach ($repositories as $repository) {
      $link = Link::fromTextAndUrl(t('Delete'), Url::fromRoute('probo.admin_config_system_probo_repositories_delete', ['rid' => $repository->rid]))->toString();
      $row = [
        [
          'data' => $repository->owner,
          'style' => 'font-family: courier, monospace;',
        ],
        [
          'data' => $repository->repository,
          'style' => 'font-family: courier, monospace;',
        ],
        [
          'data' => $repository->token,
          'style' => 'text-align: center; font-family: courier, monospace;',
        ],
        [
          'data' => $link,
          'style' => 'text-align: center; font-family: courier, monospace;',
        ],
      ];
      $rows[] = $row;
    }

    $link = '<p align="right">' . Link::fromTextAndUrl(t('Add Bucket/Repository'), Url::fromRoute('probo.admin_config_system_probo_repositories_add_new'))->toString() . '</p>';
    
    return [
      '#prefix' => $link,
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => 'THERE ARE NO REPOSITORIES CURRENTLY ASSIGNED.',
    ];
  }

  /**
   * delete_repository().
   *
   * You cannot technically delete a repository/bucket. But we can remove all
   * of it's assets and mark it as deleted. If we re-create it, we will just 
   * re-enable it. Deleting doesn't really delete. It just sets the delete flag
   * to 1 to hide it from the interface. However, it does actually delete all
   * of the assets associated with that bucket.
   *
   * @param int
   *   The repository id to remove all of the assets from and mark as deleted.
   */
  public function delete_repository($rid): RedirectResponse {
    $config = $this->config('probo.probosettings');
    $client = \Drupal::httpClient();

    // First step is to remove all of the assets from the associated bucket/repo.
    $query = \Drupal::database()->select('probo_assets', 'pa');
    $query->fields('pa', ['aid', 'rid', 'filename', 'fileid']);
    $query->addField('pr', 'owner');
    $query->addField('pr', 'repository');
    $query->addField('pr', 'token');
    $query->join('probo_repositories', 'pr', 'pr.rid = pa.rid');
    $query->condition('pa.rid', $rid);
    $assets = $query->execute()->fetchAllAssoc('aid');
    foreach ($assets as $asset) {
      $buffer = $client->delete($config->get('asset_manager_url_port') . '/buckets/' . $asset->owner . '-' . $asset->repository . '/assets/' . $asset->filename);
      $body = $buffer->getBody();
      drupal_set_message($body . ': ' . $asset->filename . ' successfully removed.');
    }

    // Mark the bucket/repo as inactive
    $query = \Drupal::database()->update('probo_repositories');
    $query->fields(['active' => 0]);
    $query->condition('rid', $rid);
    $query->execute();

    drupal_set_message('Bucket/repository has been successfully removed.');
    return new RedirectResponse(\Drupal::url('probo.admin_config_system_probo_repositories'));
  }
}