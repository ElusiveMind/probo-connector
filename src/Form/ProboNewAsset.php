<?php

namespace Drupal\probo_connector\Form;

use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\Core\Form\FormBase;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\Exception\ConnectException;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ProboNewAsset.
 */
class ProboNewAsset extends FormBase {

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
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'probo_new_asset';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $rid = NULL) {
    // We need to be able to upload insecure files and make this easy. So we just make
    // it so right here.
    $config = \Drupal::service('config.factory')->getEditable('system.file');
    $config->set('allow_insecure_uploads', TRUE)->save();

    $form['asset_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Asset File'),
      '#description' => $this->t('The asset to be uploaded to the repository. (Valid: .gz, .tgz, .zip, .sql, .tar.gz)'),
      '#upload_location' => 'public://probo-assets',
      '#upload_validators' => [
        'file_validate_extensions' => ['gz', 'tgz', 'zip', 'sql'],
      ],
      '#required' => TRUE,
    ];
    $form['owner_repository'] = [
      '#type' => 'hidden',
      '#value' => $rid,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload Asset'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $client = \Drupal::httpClient();
    $values = $form_state->getValues();
    $config = $this->config('probo.probosettings');
    $asset_receiver_url = $config->get('asset_receiver_url_port');
    $asset_receiver_token = $config->get('asset_receiver_token');

    // Handle our uploaded file and save it to the managed table.
    $upload = $form_state->getValue('asset_file');
    //$file = \Drupal\file\Entity\File::load(reset($upload));
    //$file->setPermanent();
    //$file->save();

    // Get the file system path to the file.
    $file = \Drupal\file\Entity\File::load(reset($upload));
    $filename = $file->getFileName();
    $stream_wrapper_manager = \Drupal::service('stream_wrapper_manager')->getViaUri($file->getFileUri());
    $file_path = $stream_wrapper_manager->realpath();

    // Get a list of all the owners/organizations and repositories along with the token.
    $query = $this->db->select('probo_repositories', 'pr')
      ->fields('pr', ['rid', 'owner', 'repository', 'token'])
      ->condition('pr.rid', $values['owner_repository'])
      ->orderBy('owner', 'ASC')
      ->orderBy('repository', 'ASC');
    $repositories = $query->execute()->fetchAllAssoc('rid');
    $options = [];
    foreach ($repositories as $repository) {
      $options[$repository->rid] = $repository->rid . '-' . $repository->token;
    }

    list($rid, $token) = explode('-', $options[$values['owner_repository']]);

    // Get the data contents of our file and post it to the asset manager.
    $data = file_get_contents($file_path);

    try {
      if (!empty($asset_receiver_token)) {
        $params = [
          'body' => $data,
          'timeout' => 120,
          'headers' => [
            'Authorization' => 'Bearer ' . $asset_receiver_token,
          ],
        ];
      }
      else {
        $params = [
          'timeout' => 120,
          'body' => $data,
        ];
      }
      $response = $client->post($asset_receiver_url . '/asset/' . $token . '/' . $filename, $params);
      $fileid = $response->getBody();
    }
    catch (ConnectException $e) {
      $msg = $e->getMessage();
      if (strpos($msg, 'Failed to connect')) {
        $this->messenger->addMessage('Unable to connect to ' . $asset_receiver_url. ' - please check server or setting', 'error');
        $form_state->setRedirect('probo.repository_builds', ['rid' => $rid]);
      }
      else {
        $this->messenger->addMessage($msg, 'error');
        $form_state->setRedirect('probo.repository_builds', ['rid' => $rid]);
      }
    }
    $query = \Drupal::database()->insert('probo_assets')
      ->fields(['rid', 'filename', 'fileid'])
      ->values([$rid, $filename, $fileid])
      ->execute();

      $this->messenger->addMessage('The asset ' . $filename . ' has been sucessfully uploaded.');

    // Invalidate the render cache adding the asset so it shows up.
    \Drupal::service('cache.render')->invalidateAll();

    $form_state->setRedirect('probo.repository_builds', ['rid' => $rid]);
  }
}
