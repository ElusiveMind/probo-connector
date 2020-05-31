<?php

namespace Drupal\probo\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ProboAssetReceiverForm.
 */
class ProboAssetReceiverForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'probo.asset_receiver',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'probo_asset_receiver_settings_form';
  }

  /**
   * {@inheritdoc}
   *
   * A lot of the settings for this form come directly from the asset receiver. We do not store
   * the database of assets directly in the database of Drupal so that we have a clear picture
   * of what is actually in the asset receiver database. Some items such as the asset receiver
   * access point and the token are stored locally. We can discuss caching these items in Drupal
   * at some point.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('probo.asset_receiver');

    $form['probo_asset_receiver'] = [
      '#type' => 'fieldset',
      '#title' => 'Asset Receiver Configuration',
      '#weight' => 1,
    ];

    $form['probo_asset_receiver']['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Asset Receiver URL'),
      '#description' => $this->t('The URL for the location of the asset receiver - Include the port number with colon.'),
      '#maxlength' => 255,
      '#size' => 64,
      '#default_value' => $config->get('url'),
      '#required' => TRUE,
      '#weight' => 0,
    ];
    $form['probo_asset_receiver']['token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Asset Receiver Token'),
      '#description' => $this->t('The token for your asset receiver.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('token'),
      '#required' => TRUE,
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
    $this->config('probo.asset_receiver')
      ->set('url', $form_state->getValue('url'))
      ->save();
    $this->config('probo.asset_receiver')
      ->set('token', $form_state->getValue('token'))
      ->save();
  }
}
