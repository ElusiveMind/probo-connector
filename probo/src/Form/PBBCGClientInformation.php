<?php

namespace Drupal\probo\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\Exception\ConnectException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Routing\TrustedRedirectResponse;

/**
 * Class PBBCGClientInformation.
 */
class PBBCGClientInformation extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pbbcg_client_information';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['client_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bitbucket Application Client Key'),
      '#description' => $this->t('The client key for the Bitbucket application you created for this site. Please see the instructions if you need more information.'),
      '#maxlength' => 255,
      '#size' => 64,
      '#required' => TRUE,
      '#weight' => 1,
    ];
    $form['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Probo Builds Domain'),
      '#description' => $this->t('The client secret for the Bitbucket application you created for this site. Please see the instructions if you need more information.'),
      '#maxlength' => 255,
      '#size' => 64,
      '#required' => TRUE,
      '#weight' => 2,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'submit',
      '#weight' => 3,
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
    // We need to set the client key and the client secret into a session resident cookie.
    // This way we remember them when they come back to the site for authorization.
    // Of course, we will need a fallback position to prompt them for both in the odd chance
    // it does not remember the client key and client secret. :/
    $secure = ($_SERVER['SERVER_PORT'] == 443) ? TRUE : FALSE;
    setrawcookie('bb_client_key', $form_state->getValue('client_key'), 0, '/', $_SERVER['SERVER_NAME'], $secure, TRUE);
    setrawcookie('bb_client_secret', $form_state->getValue('client_secret'), 0, '/', $_SERVER['SERVER_NAME'], $secure, TRUE);

    // Go to Bitbucket. There is no way to specify the callback in this URL, so the user will
    // have had to follow the instructions to provide the callback at the time they set up
    // the client in their application.
    header('location: https://bitbucket.org/site/oauth2/authorize?client_id=' . $form_state->getValue('client_key') . '&response_type=code');
    exit();
  }
}
