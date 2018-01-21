<?php

namespace Drupal\probo\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\Exception\ConnectException;
use Symfony\Component\HttpFoundation\RedirectResponse;


/**
 * Class PropoRepositoryForm.
 */
class PropoRepositoryForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'propo_repository_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['repository_owner'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Repository Owner'),
      '#description' => $this->t('The machine name of the repository owner.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#required' => TRUE,
    ];
    $form['repository_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Repository Name'),
      '#maxlength' => 64,
      '#size' => 64,
      '#required' => TRUE,
    ];
    $form['token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token'),
      '#description' => $this->t('The token to use for this repository. Leave blank and one will be created for you.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#required' => FALSE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
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
    // Get the values of the form and assign to an associative array.
    $values = $form_state->getValues();
    $client = \Drupal::httpClient();

    $config = $this->config('probo.probosettings');
    $asset_receiver_url = $config->get('asset_receiver_url_port');
    $asset_receiver_token = $config->get('asset_receiver_token');

    if (!empty($asset_receiver_token)) {
      $params = [
        'headers' => [
          'Authorization' => 'Bearer ' . $asset_receiver_token,
        ],
        'json' => [
          'creator' => 'Probo Drupal Module',
          'creation_date' => date('m/d/Y H:i:s')
        ],
      ];
    }
    else {
      $params = [
        'json' => [
          'creator' => 'Probo Drupal Module',
          'creation_date' => date('m/d/Y H:i:s')
        ],
      ];
    }

    try {
      $request = $client->post($asset_receiver_url . '/buckets/' . $values['repository_owner'] . '-' . $values['repository_name'], $params);
      $buffer = $request->getBody();
    }
    catch (ConnectException $e) {
      $msg = $e->getMessage();
      if (strpos($msg, 'Failed to connect')) {
        drupal_set_message('Unable to connect to ' . $asset_receiver_url . ' - please check server or setting', 'error');
        return new RedirectResponse(Url::fromRoute('probo.admin_config_system_probo_repositories')->toString());
      }
      else {
        drupal_set_message($msg, 'error');
        return new RedirectResponse(Url::fromRoute('probo.admin_config_system_probo_repositories')->toString());
      }
    }

    // If we get 'Bucket created' returned, then we were successful. Then we can create the token
    // and continue on in our process.
    if (!empty($buffer) && $buffer == 'Bucket created') {
      // If a token was not provided, then generate one and use it when
      // creating the bucket for this owner/repo.
      if (!empty($values['token'])) {
        $token = $values['token'];
      }
      else {
        // If no token provided by the user, create a random hash for our upload token.
        $token = md5(microtime() + rand(0,100000));
      }

      try {
        // Create the upload token on the server.
        if (!empty($params['json'])) {
          unset($params['json']);
        }
        $request = $client->request('POST', $asset_receiver_url . '/buckets/' . $values['repository_owner'] . '-' . $values['repository_name'] . '/token/' . $token, $params);
        $buffer = $request->getBody();
      }
      catch (ConnectException $e) {
        if (strpos($msg, 'Failed to connect')) {
          drupal_set_message('Unable to connect to ' . $asset_receiver_url . ' - please check server or setting', 'error');
          return new RedirectResponse(Url::fromRoute('probo.admin_config_system_probo_repositories')->toString());
        }
        else {
          drupal_set_message($msg, 'error');
          return new RedirectResponse(Url::fromRoute('probo.admin_config_system_probo_repositories')->toString());
        }
      }

      if (!empty($buffer) && $buffer == 'Token created') {
        // If we are here then our bucket and upload token are created, so we can add everything
        // to our database table.
        $query = \Drupal::database()->insert('probo_repositories');
        $query->fields(['owner', 'repository', 'token', 'active']);
        $query->values([$values['repository_owner'], $values['repository_name'], $token, TRUE]);
        $query->execute();

        drupal_set_message('Bucket ' . $values['repository_owner'] . '-' . $values['repository_name'] . ' has been created with the token of ' . $token);
      }
    }
    else {
      if (!empty($buffer)) {
        // If the buffer was empty, then we already have a bucket with that name and can
        // skip gracefully. Otherwise, we can't do anything and need to error.
        drupal_set_message('Creation issue: ' . $buffer, 'error');
      }
    }
    return new RedirectResponse(Url::fromRoute('probo.admin_config_system_probo_repositories')->toString());
  }
}
