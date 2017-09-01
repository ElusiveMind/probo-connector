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
    $form['probo_builds_domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Probo Builds Domain'),
      '#description' => $this->t('This is the domain name for Probo builds after the build id. Do not include the preceeding dot (.) - Include the port number and colon if needed.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('probo_builds_domain'),
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
  }

}
