<?php

namespace Drupal\probo_connector\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\Exception\ConnectException;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class PropoRepositoryForm.
 */
class PropoRepositoryForm extends FormBase {

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Database
   */
  protected $db;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * ProboRepositoryForm constructor.
   *
   * @param \Drupal\Core\Database\Database
   *  The database service
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *  The messenger service.
   */
  public function __construct(Connection $db, MessengerInterface $messenger) {
    $this->messenger = $messenger;
    $this->db = $db;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('messenger')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'propo_repository_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $rid = 0) {
    // If we are passed a repository id, then we're looking to do an update.
    $repository_owner = $repository_name = $token = $roles = $active = NULL;
    $disabled = FALSE;
    if ($rid > 0) {
      $form['rid'] = [
        '#type' => 'hidden',
        '#value' => $rid,
      ];
      // Get the repository information.
      $query = $this->db->select('probo_repositories', 'pr')
        ->fields('pr', ['rid', 'repository', 'owner', 'token', 'roles', 'active'])
        ->condition('rid', $rid);
      $repo = $query->execute()->fetchAllAssoc('rid');
      $repo = $repo[$rid];
      $disabled = TRUE;
      $repository_owner = $repo->owner;
      $repository_name = $repo->repository;
      $token = $repo->token;
      $roles = unserialize($repo->roles);
      $active = $repo->active;
    }
    $form['repository_owner'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Repository Owner'),
      '#description' => $this->t('The machine name of the repository owner.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $repository_owner,
      '#required' => TRUE,
      '#disabled' => $disabled,
    ];
    $form['repository_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Repository Name'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $repository_name,
      '#required' => TRUE,
      '#disabled' => $disabled,
    ];
    $form['token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token'),
      '#description' => $this->t('The token to use for this repository. Leave blank and one will be created for you. NEVER CHANGE THIS ONCE ASSIGNED OR YOUR ASSETS WILL BREAK.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $token,
      '#required' => FALSE,
      '#disabled' => $disabled,
    ];
    $form['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles'),
      '#description' => $this->t('The roles who have access to this repository.'),
      '#options' => user_role_names(TRUE),
      '#default_value' => $roles,
      '#required' => TRUE,
    ];
    $form['active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Active'),
      '#description' => $this->t('Is this an active repository.'),
      '#default_value' => $active,
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

    if (!empty($values['rid'])) {
      $update = TRUE;
    }
    else {
      $update = FALSE;
    }

    foreach ($values['roles'] as $role => $value) {
      if (!empty($value)) {
        $user_roles[] = $value;
      }
    }

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
      if ($update === FALSE) {
        $request = $client->post($asset_receiver_url . '/buckets/' . $values['repository_owner'] . '-' . $values['repository_name'], $params);
        $buffer = $request->getBody();
      }
    }
    catch (ConnectException $e) {
      $msg = $e->getMessage();
      if (strpos($msg, 'Failed to connect')) {
        $this->messenger->addMessage('Unable to connect to ' . $asset_receiver_url . ' - please check server or setting', 'error');
        return new RedirectResponse(Url::fromRoute('probo.admin_config_system_probo_repositories')->toString());
      }
      else {
        $this->messenger->addMessage($msg, 'error');
        return new RedirectResponse(Url::fromRoute('probo.admin_config_system_probo_repositories')->toString());
      }
    }

    // If we get 'Bucket created' returned, then we were successful. Then we can create the token
    // and continue on in our process.
    if ((!empty($buffer) && $buffer == 'Bucket created') || $update === TRUE) {
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
        if ($update === FALSE) {
          // Create the upload token on the server.
          if (!empty($params['json'])) {
            unset($params['json']);
          }
          $request = $client->request('POST', $asset_receiver_url . '/buckets/' . $values['repository_owner'] . '-' . $values['repository_name'] . '/token/' . $token, $params);
          $buffer = $request->getBody();
        }
      }
      catch (ConnectException $e) {
        if (strpos($msg, 'Failed to connect')) {
          $this->messenger->addMessage('Unable to connect to ' . $asset_receiver_url . ' - please check server or setting', 'error');
          return new RedirectResponse(Url::fromRoute('probo.admin_config_system_probo_repositories')->toString());
        }
        else {
          $this->messenger->addMessage($msg, 'error');
          return new RedirectResponse(Url::fromRoute('probo.admin_config_system_probo_repositories')->toString());
        }
      }

      if ($update === TRUE) {
        $query = \Drupal::database()->update('probo_repositories')
          ->condition('rid', $values['rid'])
          ->fields(['roles' => serialize($user_roles), 'active' => $values['active']])
          ->execute();
          $this->messenger->addMessage('Bucket ' . $values['repository_owner'] . '-' . $values['repository_name'] . ' has been updated');
      } elseif (!empty($buffer) && $buffer == 'Token created') {
        // If we are here then our bucket and upload token are created, so we can add everything
        // to our database table.
        $query = \Drupal::database()->insert('probo_repositories');
        $query->fields(['owner', 'repository', 'token', 'roles', 'active']);
        $query->values([$values['repository_owner'], $values['repository_name'], $token, serialize($user_roles), TRUE]);
        $query->execute();

        $this->messenger->addMessage('Bucket ' . $values['repository_owner'] . '-' . $values['repository_name'] . ' has been created with the token of ' . $token);
      }
    }
    else {
      if (!empty($buffer)) {
        // If the buffer was empty, then we already have a bucket with that name and can
        // skip gracefully. Otherwise, we can't do anything and need to error.
        $this->messenger->addMessage('Creation issue: ' . $buffer, 'error');
      }
    }
    return new RedirectResponse(Url::fromRoute('probo.admin_config_system_probo_repositories')->toString());
  }
}
