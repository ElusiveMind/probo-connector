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
    $form['probo_asset_receiver'] = [
      '#type' => 'fieldset',
      '#title' => 'Asset Receiver Configuration',
      '#weight' => 1,
    ];
    $form['probo_loom'] = [
      '#type' => 'fieldset',
      '#title' => 'Loom Service Configuration',
      '#weight' => 2,
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
    $form['probo_asset_receiver']['asset_receiver_url_port'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Asset Receiver URL'),
      '#description' => $this->t('The URL for the location of the asset receiver - Include the port number with colon.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('asset_receiver_url_port'),
      '#weight' => 0,
    ];
    $form['probo_asset_receiver']['asset_receiver_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Asset Receiver Token'),
      '#description' => $this->t('The token for your asset receiver if required (STRONGLY RECOMMENDED).'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('asset_receiver_token'),
      '#weight' => 1,
    ];
    $form['probo_loom']['probo_loom_stream_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Probo Loom URL'),
      '#description' => $this->t('The URL for the location of the loom service - Include the port number with colon.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('probo_loom_stream_url'),
      '#weight' => 0,
    ];
    $form['probo_loom']['probo_loom_stream_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Loom Bearer Token'),
      '#description' => $this->t('The token configured for use with your loom service.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('probo_loom_stream_token'),
      '#weight' => 1,
    ];
    $form['probo_module_debug_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Module Debug Mode'),
      '#description' => $this->t('Placing the module in debug mode will place a lot of information in your logged messages. Not recommended for production.'),
      '#default_value' => $config->get('probo_module_debug_mode'),
      '#weight' => 3,
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
      ->set('asset_receiver_url_port', $form_state->getValue('asset_receiver_url_port'))
      ->save();
    $this->config('probo.probosettings')
      ->set('asset_receiver_token', $form_state->getValue('asset_receiver_token'))
      ->save();
    $this->config('probo.probosettings')
      ->set('probo_loom_stream_url', $form_state->getValue('probo_loom_stream_url'))
      ->save();
    $this->config('probo.probosettings')
      ->set('probo_loom_stream_token', $form_state->getValue('probo_loom_stream_token'))
      ->save();
    $this->config('probo.probosettings')
      ->set('probo_module_debug_mode', $form_state->getValue('probo_module_debug_mode'))
      ->save();
  }
}
