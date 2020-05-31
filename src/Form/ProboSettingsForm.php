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
      'probo.settings',
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
    $config = $this->config('probo.settings');
    $form['probo_general'] = [
      '#type' => 'fieldset',
      '#title' => 'General Probo Server Settings',
      '#weight' => 0,
    ];
    $form['probo_loom'] = [
      '#type' => 'fieldset',
      '#title' => 'Loom Service Configuration',
      '#weight' => 1,
    ];
    $form['probo_general']['base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Probo Web Server Base URL'),
      '#description' => $this->t('The base URL of this web site.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('base_url'),
      '#required' => TRUE,
      '#weight' => 1,
    ];
    $form['probo_general']['mailcatcher_domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Probo MailCatcher Domain'),
      '#description' => $this->t('This is the domain name (or subdomain) for the MailCatcher port. Do not include the preceeding dot (.)'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('mailcatcher_domain'),
      '#required' => TRUE,
      '#weight' => 1,
    ];
    $form['probo_general']['solr_domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Probo SOLR Domain'),
      '#description' => $this->t('This is the domain name (or subdomain) for the SOLR port. Do not include the preceeding dot (.)'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('solr_domain'),
      '#required' => TRUE,
      '#weight' => 2,
    ];
    $form['probo_general']['builds_domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Probo Builds Domain'),
      '#description' => $this->t('This is the domain name for Probo builds after the build id. Do not include the preceeding dot (.) - Include the port number and colon if needed.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('builds_domain'),
      '#required' => TRUE,
      '#weight' => 3,
    ];
    $form['probo_general']['builds_protocol'] = [
      '#type' => 'select',
      '#title' => $this->t('Build Domain Protocol'),
      '#options' => [
        'http' => $this->t('http'),
        'https' => $this->t('https'),
      ],
      '#default_value' => $config->get('builds_protocol'),
      '#required' => TRUE,
      '#weight' => 4,
    ];
    $form['probo_loom']['loom_stream_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Probo Loom URL'),
      '#description' => $this->t('The URL for the location of the loom service - Include the port number with colon.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('loom_stream_url'),
      '#required' => TRUE,
      '#weight' => 0,
    ];
    $form['probo_loom']['loom_stream_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Loom Bearer Token'),
      '#description' => $this->t('The token configured for use with your loom service.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('loom_stream_token'),
      '#required' => TRUE,
      '#weight' => 1,
    ];
    $form['api_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Token'),
      '#description' => $this->t('The token to be used by any app connecting to the service points of this web site.'),
      '#maxlength' => 32,
      '#size' => 32,
      '#default_value' => $config->get('api_token'),
      '#required' => TRUE,
      '#weight' => 4,
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
    $this->config('probo.settings')
      ->set('builds_domain', $form_state->getValue('builds_domain'))
      ->save();
    $this->config('probo.settings')
      ->set('mailcatcher_domain', $form_state->getValue('mailcatcher_domain'))
      ->save();
    $this->config('probo.settings')
      ->set('solr_domain', $form_state->getValue('solr_domain'))
      ->save();
    $this->config('probo.settings')
      ->set('builds_protocol', $form_state->getValue('builds_protocol'))
      ->save();
    $this->config('probo.settings')
      ->set('loom_stream_url', $form_state->getValue('loom_stream_url'))
      ->save();
    $this->config('probo.settings')
      ->set('loom_stream_token', $form_state->getValue('loom_stream_token'))
      ->save();
    $this->config('probo.settings')
      ->set('api_token', $form_state->getValue('api_token'))
      ->save();
    $this->config('probo.settings')
      ->set('base_url', $form_state->getValue('base_url'))
      ->save();
  }
}
