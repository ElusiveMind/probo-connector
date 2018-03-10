<?php

namespace Drupal\probo\Form;

use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\Core\Form\FormBase;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\Exception\ConnectException;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class ProboNewAsset.
 */
class ProboNewAsset extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'probo_new_asset';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get a list of all the owners/organizations and repositories along with the token.
    $query = \Drupal::database()->select('probo_repositories', 'pr')
      ->fields('pr', ['rid', 'owner', 'repository', 'token'])
      ->condition('pr.active', TRUE)
      ->orderBy('owner', 'ASC')
      ->orderBy('repository', 'ASC');
    $repositories = $query->execute()->fetchAllAssoc('rid');
    $options = [];
    foreach ($repositories as $repository) {
      $options[$repository->rid . '-' . $repository->token] = $repository->owner . '-' . $repository->repository;
    }

    // We need to be able to upload insecure files and make this easy. So we just make
    // it so right here.
    $config = \Drupal::service('config.factory')->getEditable('system.file');
    $config->set('allow_insecure_uploads', TRUE)->save();

    $form['asset_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Asset File'),
      '#description' => $this->t('The asset or file to be uploaded to the respective bucket. (Valid: .gz, .tgz, .zip, .sql'),
      '#upload_location' => 'public://probo-assets',
      '#upload_validators' => [
        'file_validate_extensions' => ['gz', 'tgz', 'zip', 'sql'],
      ],
      '#required' => TRUE,
    ];
    $form['owner_repository'] = [
      '#type' => 'select',
      '#title' => $this->t('Owner/Repository'),
      '#description' => $this->t('Select a repository for this asset.'),
      '#options' => $options,
      '#size' => 1,
      '#required' => TRUE,
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
    $file = \Drupal\file\Entity\File::load(reset($upload));
    $file->setPermanent();
    $file->save();

    // Get the file system path to the file.
    $file = \Drupal\file\Entity\File::load(reset($upload));
    $filename = $file->getFileName();
    $stream_wrapper_manager = \Drupal::service('stream_wrapper_manager')->getViaUri($file->getFileUri());
    $file_path = $stream_wrapper_manager->realpath();

    list($rid, $token) = explode('-', $values['owner_repository']);

    // Get the data contents of our file and post it to the asset manager.
    $data = file_get_contents($file_path);

    try {
      if (!empty($asset_receiver_token)) {
        $params = [
          'body' => $data,
          'headers' => [
            'Authorization' => 'Bearer ' . $asset_receiver_token,
          ],
        ];
      }
      else {
        $params = [
          'body' => $data,
        ];
      }
      $response = $client->post($asset_receiver_url . '/asset/' . $token . '/' . $filename, $params);
      $fileid = $response->getBody();
    }
    catch (ConnectException $e) {
      $msg = $e->getMessage();
      if (strpos($msg, 'Failed to connect')) {
        drupal_set_message('Unable to connect to ' . $asset_receiver_url. ' - please check server or setting', 'error');
        return new RedirectResponse(Url::fromRoute('probo.admin_config_system_probo_assets')->toString());
      }
      else {
        drupal_set_message($msg, 'error');
        return new RedirectResponse(Url::fromRoute('probo.admin_config_system_probo_assets')->toString());
      }
    }
    $query = \Drupal::database()->insert('probo_assets')
      ->fields(['rid', 'filename', 'fileid'])
      ->values([$rid, $filename, $fileid])
      ->execute();

    drupal_set_message('The asset ' . $filename . ' has been sucessfully uploaded.');
    return new RedirectResponse(Url::fromRoute('probo.admin_config_system_probo_assets')->toString());
  }
}
