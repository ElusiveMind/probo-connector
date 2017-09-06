<?php

namespace Drupal\probo\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

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
      ->orderBy('owner', 'ASC')
      ->orderBy('repository', 'ASC');
    $repositories = $query->execute()->fetchAllAssoc('rid');
    $options = [];
    foreach ($repositories as $repository) {
      $options[$repository->token] = $repository->owner . '-' . $repository->repository;
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
    // Display result.
    foreach ($form_state->getValues() as $key => $value) {
      drupal_set_message($key . ': ' . $value);
    }

  }
}
