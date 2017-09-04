<?php

namespace Drupal\probo\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ProboSettingsForm.
 */
class ProboSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'probo.probosettings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'probo_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('probo.probosettings');
    $form['probo_general'] = [
      '#type' => 'fieldset',
      '#title' => 'General Probo Server Settings',
      '#weight' => 0,
    ];
    $form['probo_asset_manager'] = [
      '#type' => 'fieldset',
      '#title' => 'Asset Manager Configuration',
      '#weight' => 1,
    ];
    $form['probo_general']['probo_builds_domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Probo Builds Domain'),
      '#description' => $this->t('This is the domain name for Probo builds after the build id. Do not include the preceeding dot (.) - Include the port number and colon if needed.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('probo_builds_domain'),
      '#weight' => 0,
    ];
    $form['probo_asset_manager']['asset_manager_url_port'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Asset Manager URL'),
      '#description' => $this->t('The URL for the location of the asset manager - Include the port number and colon if needed.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('asset_manager_url_port'),
      '#weight' => 0,
    ];
    $form['probo_asset_manager']['asset_manager_upload_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Upload Token'),
      '#description' => $this->t('The upload token for your asset manager if required (STRONGLY RECOMMENDED).'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('asset_manager_upload_token'),
      '#weight' => 1,
    ];
    return parent::buildForm($form, $form_state);
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
    parent::submitForm($form, $form_state);
    $this->config('probo.probosettings')
      ->set('probo_builds_domain', $form_state->getValue('probo_builds_domain'))
      ->save();
    $this->config('probo.probosettings')
      ->set('asset_manager_url_port', $form_state->getValue('asset_manager_url_port'))
      ->save();
    $this->config('probo.probosettings')
      ->set('asset_manager_upload_token', $form_state->getValue('asset_manager_upload_token'))
      ->save();
  }
}
