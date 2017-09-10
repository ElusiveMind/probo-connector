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
      $response = $client->post($config->get('asset_manager_url_port') . '/asset/' . $token . '/' . $filename, ['body' => $data]);
      $fileid = $response->getBody();
    }
    catch (ConnectException $e) {
      $msg = $e->getMessage();
      if (strpos($msg, 'Failed to connect')) {
        drupal_set_message('Unable to connect to ' . $config->get('asset_manager_url_port'). ' - please check server or setting', 'error');
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
