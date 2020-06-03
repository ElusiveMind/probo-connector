<?php

namespace Drupal\probo\Controller;

use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Form\ConfigFormBase;
use GuzzleHttp\Exception\ConnectException;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\probo\Controller\ProboAssetController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\probo\Controller\ProboBitbucketController;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
 

/**
 * Class ProboRepositoryController.
 */
class ProboRepositoryController extends ControllerBase {
 
  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Database
   */
  protected $db;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * ProboRepositoryController constructor.
   *
   * @param \Drupal\Core\Database\Database
   *  The database service
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *  The messenger service.
   */
  public function __construct(Connection $db, MessengerInterface $messenger) {
    $this->messenger = $messenger;
    $this->db = $db;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('messenger')
    );
  }


  /**
   * display_repositories.
   *
   * @return array
   *   Return render array of a table of elements that make up the list
   *   of available repositories or an empty list.
   */
  public function display_repositories() {
    $store = \Drupal::service('tempstore.private')->get('probo');
    $enabled_services = $store->get('services');
    $services = [
      'bitbucket' => 'Bitbucket',
      'github' => 'GitHub',
      'gitlab' => 'GitLab'
    ];
    $html = '';
    $tables = [];

    /**
     * Iterate through our services, pull a list of enabled Probo accounts for
     * each service. Display a helpful message for each service depending on
     * if we're logged in or not, or have any enabled Probo Accounts
     */
    foreach ($services as $service => $label) {
      if (empty($enabled_services[$service])) {
        continue;
      }
    
      $rows = [];

      $header = [
        ['data' => $this->t('Owner/Team')],
        ['data' => $this->t('Repository')],
        ['data' => $this->t('Space Used'),
         'style' => 'text-align: center'],
        ['data' => $this->t('Actions'),
         'style' => 'text-align: center'],
      ];

      $query = $this->db->select('probo_repositories', 'pr')
        ->fields('pr')
        ->condition('service', $service, "=")
        ->orderBy('owner', 'ASC')
        ->orderBy('repository', 'ASC');
      $repositories = $query->execute()->fetchAllAssoc('rid');
      foreach ($repositories as $rid => $repository) {
        $active_style = ($repository->active == 1) ? 'repository-active' : 'repository-inactive';
        $row = [
          [
            'data' => $repository->owner . '/' . $repository->team,
          ],
          [
            'data' => $repositopry->repository,
          ],
          [
            'data' => $calculated_space,
            'style' => 'text-align: center;',
          ],
          [
            'data' => 'Builds | Deactivate',
            'style' => 'text-align: center;',
          ]
        ];
        $rows[] = [
          'data' => $row,
          'class' => [$active_style],
        ];
      }

      $prefix = '<a href="probo/authorize-'.$service.'"><img class="' . $service . '-logo-image" src="/modules/probo-drupal-org/probo/images/' . $service . '-text.png"></a>';
      $empty = '<div class="no-service-contents">';
      $empty .= $this->t('There are no active repositories for ' . $label . '. To use repositories for ' . $label . ', click the logo above.');
      $empty .= '</div>';

      $footer = [];

      $table = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#footer' => $footer,
        '#empty' => new FormattableMarkup($empty, []),
        '#prefix' => new FormattableMarkup($prefix, []),
      ];
      $html .= \Drupal::service('renderer')->renderRoot($table);
    }

    return [
      '#markup' => $html,
      '#attached' => [
        'library' => [
          'probo/global-styling',
        ],
      ],
    ];

    exit();


    /*
      $build_link = '/probo/' . $repository->rid;
      $query = \Drupal::database()->select('probo_builds', 'pb')
        ->fields('pb', ['id', 'rid', 'build_size'])
        ->condition('rid', $repository->rid);
      $active_builds = $query->execute()->fetchAllAssoc('id');
      $builds = $active_builds;
      $active_builds = count($active_builds);

      $size = 0;
      foreach ($builds as $active_build) {
        $size += $active_build->build_size;
      }
      $build_size = $size/(1024*1024);
      if ($build_size > 1000) {
        $build_size = $build_size / 1024;
        $build_unit = "GB";
      }
      else {
        $build_unit = "MB";
      }

      $row = [
        [
          'data' => $repository->repository,
          'class' => 'td-repository',
          'onclick' => "window.location.href='" . $build_link . "'",
        ],
        [
          'data' => number_format($build_size, 2) . ' ' . $build_unit,
          'class' => 'td-active-builds center',
          'onclick' => "window.location.href='" . $build_link . "'",
        ],
        [
          'data' => $active_builds,
          'class' => 'td-active-builds center',
          'onclick' => "window.location.href='" . $build_link . "'",
        ],
      ];
      $rows[] = $row;
    }

    return [
      '#type' => 'table',
      '#attributes' => ['class' => ['table table-striped']],
      '#prefix' => NULL,
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => 'THERE ARE NO REPOSITORIES CURRENTLY ASSIGNED.',
    ];
    */
  }

  /**
   * repository_builds.
   *
   * @param int
   *  The repository id to view the builds for.
   *
   * @return array
   *   Return render array for our ReactJS component and the interface for
   *   maintaining the assets for this repository.
   */
  public function repository_builds($rid) {
    $config = $this->config('probo.probosettings');
    $probo_base_url = $config->get('base_url');

    $asset_table = NULL;
    $user = \Drupal::currentUser();
    $has_asset_permission = $user->hasPermission('access probo assets');
    if ($has_asset_permission === TRUE) {
      $query = $this->db->select('probo_assets', 'pa');
      $query->fields('pa', ['aid', 'rid', 'filename']);
      $query->addField('pr', 'owner');
      $query->addField('pr', 'repository');
      $query->leftJoin('probo_repositories', 'pr', 'pr.rid = pa.rid');
      $query->condition('pa.rid', $rid, '=');
      $query->orderBy('pr.owner', 'ASC');
      $query->orderBy('pr.repository', 'ASC');    
      $assets = $query->execute()->fetchAllAssoc('aid');

      $header = [
        [
          'data' => $this->t('Asset File Name'),
          'class' => 'probo-purple-dark probo-text-soft-peach repository bold',
        ],
        [
          'data' => '',
          'class' => 'probo-purple-dark probo-text-soft-peach repository right-text',
        ],
        [
          'data' => '',
          'class' => 'probo-purple-dark probo-text-soft-peach repository right-text',
        ],
      ];

      $rows = [];
      foreach ($assets as $asset) {
        $delete = Link::fromTextAndUrl($this->t('Delete'), Url::fromRoute('probo.probo_asset_delete', ['aid' => $asset->aid, 'rid' => $rid]))->toString();
        $download = Link::fromTextAndUrl($this->t('Download'), Url::fromRoute('probo.probo_asset_download', ['aid' => $asset->aid]))->toString();
        $row = [
          [
            'data' => $asset->filename,
          ],
          [
            'data' => new FormattableMarkup($delete, []),
            'class' => 'right-text',
          ],
          [ 
            'data' => new FormattableMarkup($download, []),
            'class' => 'right-text',
          ],
        ];
        $rows[] = $row;
      }

      $footer = [];
      $add_new = '<p align="right">' . Link::fromTextAndUrl(t('Add New Asset'), Url::fromRoute('probo.probo_asset_add',['rid' => $rid]))->toString() . '</p>';
    
      $asset_table = [
        '#attributes' => ['class' => ['table table-striped']],
        '#suffix' => $add_new,
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#footer' => $footer,
        '#empty' => 'THERE ARE NO ASSETS UPLOADED FOR THIS REPOSITORY.',
      ];
    }

    return [
      '#theme' => 'probo_reactjs',
      '#url' => $probo_base_url,
      '#rid' => $rid,
      '#asset_table' => $asset_table,
    ];
  }

  /**
   * admin_display_repositories.
   *
   * @return array
   *   Return render array of a table of elements that make up the list
   *   of available repositories or an empty list.
   */
   public function admin_display_repositories() {
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
        'data' => $this->t('Edit'),
        'style' => 'text-align: center',
      ],
      [
        'data' => $this->t('Delete'),
        'style' => 'text-align: center',
      ],
    ];

    $rows = [];
    foreach ($repositories as $repository) {
      $edit = Link::fromTextAndUrl(t('Edit'), Url::fromRoute('probo.admin_config_system_probo_repositories_update', ['rid' => $repository->rid]))->toString();
      $delete = Link::fromTextAndUrl(t('Delete'), Url::fromRoute('probo.admin_config_system_probo_repositories_delete', ['rid' => $repository->rid]))->toString();
      $row = [
        [
          'data' => $repository->owner,
          'style' => '',
        ],
        [
          'data' => $repository->repository,
          'style' => '',
        ],
        [
          'data' => $repository->token,
          'style' => 'text-align: center;',
        ],
        [
          'data' => $edit,
          'style' => 'text-align: center;',
        ],
        [
          'data' => $delete,
          'style' => 'text-align: center;',
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
      '#empty' => 'THERE ARE NO REPOSITORIES.',
    ];
  }

  /**
   * admin_delete_repository().
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
  public function admin_delete_repository($rid) {
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
      try {
        $buffer = $client->delete($config->get('asset_receiver_url_port') . '/buckets/' . $asset->owner . '-' . $asset->repository . '/assets/' . $asset->filename);
        $body = $buffer->getBody();
      }
      catch (ConnectException $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Failed to connect')) {
          $this->messenger->addMessage('Unable to connect to ' . $config->get('asset_receiver_url_port'). ' - please check server or setting', 'error');
          return new RedirectResponse(Url::fromRoute('probo.admin_config_system_probo_repositories')->toString());
        }
      }
      $this->messenger->addMessage($body . ': ' . $asset->filename . ' successfully removed.');
    }

    // Mark the bucket/repo as inactive
    $query = $this->db->update('probo_repositories');
    $query->fields(['active' => 0]);
    $query->condition('rid', $rid);
    $query->execute();

    $this->messenger->addMessage('Bucket/repository has been successfully removed.');
    return new RedirectResponse(Url::fromRoute('probo.admin_config_system_probo_repositories')->toString());
  }

  /**
   * delete_asset($aid)
   * Remove an asset from the asset handler.
   *
   * @param int $aid
   *   The id of the asset we are removing.
   * @return RedirectResponse
   *   Redirect to the list of assets.
   */
  public function delete_asset($aid, $rid) {
    $client = \Drupal::httpClient();
    $config = $this->config('probo.probosettings');
    $asset_receiver_url = $config->get('asset_receiver_url_port');
    $asset_receiver_token = $config->get('asset_receiver_token');

    $params = (!empty($asset_receiver_token)) ? ['headers' => ['Authorization' => 'Bearer ' . $asset_receiver_token]] : [];

    // Get the filename, owner/organization and repository for deleting the asset.
    $query = $this->db->select('probo_assets', 'pa');
    $query->addField('pa', 'filename');
    $query->addField('pr', 'owner');
    $query->addField('pr', 'repository');
    $query->orderBy('pr.owner', 'ASC');
    $query->orderBy('pr.repository', 'ASC');
    $query->join('probo_repositories', 'pr', 'pr.rid = pa.rid');
    $query->condition('pa.aid', $aid);
    $assets = $query->execute()->fetchAllAssoc('rid');
    $assets = array_pop($assets);

    try {
      $response = $client->request('DELETE', $asset_receiver_url . '/buckets/' . $assets->owner . '-' . $assets->repository . '/assets/ ' . $assets->filename, $params);
      $buffer = $response->getBody();
    }
    catch (ConnectException $e) {
      $msg = $e->getMessage();
      if (strpos($msg, 'Failed to connect')) {
        $this->messenger->addMessage('Unable to connect to ' . $config->get('asset_receiver_url_port'). ' - please check server or setting', 'error');
        return new RedirectResponse(Url::fromRoute('probo.repository_builds')->toString());
      }
    }

    // Remove the reference from the table.
    $query = $this->db->delete('probo_assets')
      ->condition('aid', $aid)
      ->execute();

    $this->messenger->addMessage('The ' . $asset->filename . ' in the ' . $assets->owner . '-' . $assets->repository . ' bucket has been successfully deleted.');
    return new RedirectResponse(Url::fromRoute('probo.repository_builds', ['rid' => $rid])->toString());
  }

  /**
   * download_asset($aid)
   * Make a call to the asset received daemon and get the file and deliver it to the user.
   *
   * @param int $aid
   *   The id of the asset we are downloading.
   * @return RedirectResponse
   *   Redirect to the URL on the asset manager to begin the download.
   */
  public function download_asset($aid) {
    $config = $this->config('probo.probosettings');

    // Get the filename, owner/organization and repository for deleting the asset.
    $query = $this->db->select('probo_assets', 'pa');
    $query->addField('pa', 'filename');
    $query->addField('pr', 'owner');
    $query->addField('pr', 'repository');
    $query->orderBy('pr.owner', 'ASC');
    $query->orderBy('pr.repository', 'ASC');
    $query->join('probo_repositories', 'pr', 'pr.rid = pa.rid');
    $query->condition('pa.aid', $aid);
    $assets = $query->execute()->fetchAllAssoc('rid');
    $assets = array_pop($assets);

    // Construct the URL and then redirect to it to begint the download.
    $url = $config->get('asset_receiver_url_port') . '/asset/' . $assets->owner . '-' . $assets->repository . '/' . $assets->filename;
    return new TrustedRedirectResponse($url);
  }
}