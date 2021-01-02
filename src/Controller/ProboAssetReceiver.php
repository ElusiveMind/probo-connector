<?php

namespace Drupal\probo_connector\Controller;

use Drupal\Core\Url;
use Drupal\Core\Link;

//use GuzzleHttp\Exception\ConnectException;
//use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\RedirectResponse;
//use Drupal\Core\Routing\TrustedRedirectResponse;
//use Drupal\Component\Render\FormattableMarkup;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Database\Connection;
use Drupal\probo\Controller\ProboAssetReceiverAPI;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ProboAssetReceiver.
 */
class ProboAssetReceiver extends ControllerBase {

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
   * ProboAssetReceiver constructor.
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
   * list_assets().
   *
   * @param string bucket
   *   The bucket for which we wish to list all of our assets.
   * @return array
   *   Return a render array that is a table of assets.
   */
  public function list_assets($bucket) {
    // Check to be sure we're all set up before we do anything Probo related. Go to the
    // home page and display messages if not.
    $check = probo_gatekeeper();
    if ($check === FALSE) {
      $url = Url::fromRoute('<front>')->toString();
      return new RedirectResponse($url);
    }

    $obj = new ProboAssetReceiverAPI;
    $header = [
      [
        'data' => $this->t('Owner/Repository'),
      ],
      [
        'data' => $this->t('File Name'),
      ],
      [
        'data' => $this->t('File Size'),
      ],
      [
        'data' => $this->t('Actions'),
        'style' => 'text-align: center',
      ],
    ];
    //print "<pre>"; print_r($obj->createBucketToken('bar', 'testtoken3'));
    //print "<pre>"; print_r($obj->listBucketTokens('bar'));
    //print "<pre>"; print_r($obj->deleteBucketToken('bar', 'testtoken2'));
    //print "<pre>"; print_r($obj->listBucketTokens('bar'));exit();
    //print "<pre>"; print_r($obj->createBucket('bar', ['key1' => 'value1', 'key2' => 'value2']));exit();
    //print "<pre>"; print_r($obj->getBucket('bar'));exit();
    //print "<pre>"; print_r($obj->getBuckets());exit();
    //print "<pre>"; print_r($obj->setUploadStatus(1));

    //$data = file_get_contents('/var/www/html/web/usda_oig-oig.2020-02-25.sql.gz');
    //print "<pre>"; print_r($obj->receiveFileAsset('30acb03dcae03ce5ec76f6edfc0d0b93','usda_oig-oig.2020-02-25.sql.gz', $data));exit();
    //print "<pre>"; print_r($obj->listAssetInfo('test', 'usda_oig-oig.2020-02-25.sql.gz')); exit();
    //exit();
    //print "<pre>"; print_r($obj->deleteAssetFromBucket('test', 'usda_oig-oig.2020-02-25.sql.gz')); exit();
    //print "<pre>"; print_r($obj->assetMetadataByBucket('test')); exit();

    //print "<pre>"; print_r($obj->setUploadStatus(1));
    //pr/nt "<pre>"; print_r($obj->getUploadStatus());
    print "<pre>"; print_r($obj->getDataExport()); exit();

    $rows = [];
    foreach ($assets as $asset) {
      //$link = Link::fromTextAndUrl(t('Delete'), Url::fromRoute('probo.admin_config_system_probo_asset_delete', ['aid' => $asset->aid]))->toString();
      //$download = Link::fromTextAndUrl(t('Download'), Url::fromRoute('probo.admin_config_system_probo_asset_download', ['aid' => $asset->aid]))->toString();

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

  /**
   * delete_asset($aid)
   * Remove an asset from the asset handler.
   *
   * @param int $aid
   *   The id of the asset we are removing.
   * @return RedirectResponse
   *   Redirect to the list of assets.
   */
  public function delete_asset($aid) {
    $client = \Drupal::httpClient();
    $config = $this->config('probo.asset_receiver');
    $asset_receiver_url = $config->get('url');
    $asset_receiver_token = $config->get('token');

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

    // Invalidate the render cache after removing the asset so it does not show anymore.
    \Drupal::service('cache.render')->invalidateAll();
    
    return new RedirectResponse(Url::fromRoute('probo.repository_builds')->toString());
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
    $config = $this->config('probo.asset_receiver');

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
    $url = $config->get('url') . '/asset/' . $assets->owner . '-' . $assets->repository . '/' . $assets->filename;
    return new TrustedRedirectResponse($url);
  }

}
