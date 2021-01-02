<?php

namespace Drupal\probo_connector\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class JiraSettingsForm.
 */
class JiraSettingsForm extends ConfigFormBase {

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
    return 'jira_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('probo.probosettings');
    $form['jira_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('JIRA URL'),
      '#description' => $this->t('The base URL to your instance of JIRA.'),
      '#maxlength' => 255,
      '#size' => 64,
      '#default_value' => $config->get('jira_url'),
      '#weight' => 1,
    ];
    $form['jira_api_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('JIRA API Username'),
      '#description' => $this->t('Your JIRA API Authentication Username.'),
      '#maxlength' => 255,
      '#size' => 64,
      '#default_value' => $config->get('jira_api_username'),
      '#weight' => 2,
    ];
    $form['jira_api_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('JIRA API Token'),
      '#description' => $this->t('Your JIRA API Authentication Token.'),
      '#maxlength' => 255,
      '#size' => 64,
      '#default_value' => $config->get('jira_api_token'),
      '#weight' => 2,
    ];
    $form['jira_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('JIRA Integration Enabled'),
      '#description' => $this->t('Check this box to enable posting to JIRA based on ticket number. Please provide API information above.'),
      '#default_value' => $config->get('jira_enabled'),
      '#weight' => 5,
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
      ->set('jira_url', $form_state->getValue('jira_url'))
      ->save();
    $this->config('probo.probosettings')
      ->set('jira_api_token', $form_state->getValue('jira_api_token'))
      ->save();
    $this->config('probo.probosettings')
      ->set('jira_api_username', $form_state->getValue('jira_api_username'))
      ->save();
    $this->config('probo.probosettings')
      ->set('jira_enabled', $form_state->getValue('jira_enabled'))
      ->save();
  }
}
