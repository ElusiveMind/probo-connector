<?php

namespace Drupal\probo_connector\Controller;

use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\Exception\ConnectException;
//use Drupal\Core\StringTranslation\StringTranslationTrait;
//use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Component\Render\FormattableMarkup;

use Stevenmaguire\OAuth2\Client\Provider\Bitbucket;
use Bitbucket\Client;
use Bitbucket\Exception\ClientErrorException;
use Drupal\probo\Objects\ProboTeam;
use Drupal\probo\Objects\ProboRepository;

/**
 * Class ProboBitbucketController.
 */
class ProboBitbucketController extends ControllerBase {

  /**
   * get_association().
   * Find out if we have any current repository associations. If we have one for
   * Bitbucket then return true so we can skip all that. This only gets called for
   * requests to get the authorization code.
   * 
   * @return bool $bitbucket_configured
   *   TRUE if Bitbucket is already logged into, FALSE otherwise.
   */
  private function get_association() {
    $store = \Drupal::service('tempstore.private')->get('probo');
    $services = $store->get('services');
    if (!empty($services['bitbucket'])) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * get_authorization_code().
   * When accessing this for the first time, they have to accept the Bitbucket app
   * and this will pass the needed code back to Open Source Probo for handling.
   */
  public function get_authorization_code() {
    if (empty($this->get_association())) {
      $redirect_uri = Url::fromRoute('probo.bitbucket_process_code');
      $provider = $this->get_provider($redirect_uri);
      $authorization_url = $provider->getAuthorizationUrl();
      return new TrustedRedirectResponse($authorization_url);
    }
    return new TrustedRedirectResponse('/probo/authorize-bitbucket/repositories');
  }

  /**
   * associate_user().
   * When a user accepts the Bitbucket app, it creates a reference to their current
   * Drupal user so that the accounts are tied together. It includes their refresh
   * token for future authentication.
   * 
   * When completed, take them to a list of repositories they own so they can select
   * ones they wish to use with Open Source Probo.
   */
  public function associate_user() {
    $redirect_uri = Url::fromRoute('probo.bitbucket_process_code');
    $provider = $this->get_provider($redirect_uri);

    $uid = \Drupal::currentUser()->id();
    $query = \Drupal::database()->select('probo_bitbucket', 'pb')
      ->fields('pb')
      ->condition('uid', $uid, '=');
    $data = $query->execute();
    $results = $data->fetchAll(\PDO::FETCH_OBJ);
    if (!empty($results)) {
      // Try to get an access token (using the authorization code grant)
      $auth = $provider->getAccessToken('refresh_token', ['refresh_token' => $results[0]->refresh_token]);
      $token = $auth->getToken();
      $refresh_token = $auth->getRefreshToken();
      /** User has already logged into Bitbucket, just update the tokens */
      $query = \Drupal::database()->update('probo_bitbucket');
      $query->condition('uid', $uid, '=');
      $query->fields([
        'access_token' => $token,
        'refresh_token' => $refresh_token,
      ]);
      $query->execute();
    }
    else {
      // If, by some reason, we do not have a code from Bitbucket, go to the home page.
      if (empty($_GET['code'])) {
        new TrustedRedirectResponse(Url::fromRoute('probo.home')->toString);
      }
      // Try to get an access token (using the authorization code grant)
      $auth = $provider->getAccessToken('authorization_code', ['code' => $_GET['code']]);
      $token = $auth->getToken();
      $refresh_token = $auth->getRefreshToken();

      $bitbucket = new Client();
      $bitbucket->authenticate(Client::AUTH_OAUTH_TOKEN, $token);
      $bitbucket_user = $bitbucket->currentUser()->show();

      /** We need to upsert the user here. */
      $query = \Drupal::database()->select('probo_bitbucket', 'pb');
      $query->fields('pb', ['uid', 'account_id']);
      $query->condition('account_id', $bitbucket_user['account_id'], '=');
      $user = $query->execute()->fetchAllAssoc('uid');
      if (count($user) > 0) {
        /** User has already logged into Bitbucket, just update the tokens */
        $query = \Drupal::database()->update('probo_bitbucket', 'pb');
        $query->condition('account_id', $bitbucket_user['account_id'], '=');
        $query->fields([
          'access_token' => $token,
          'refresh_token' => $refresh_token,
          'username' => $bitbucket['username'],
          'display_name' => $bitbucket_user['display_name'],
        ]);
        $query->execute();
      }
      else {
        /** User has not logged in to Bitbucket, create the record. */
        $uid = \Drupal::currentUser()->id();
        $query = \Drupal::database()->insert('probo_bitbucket');
        $query->fields(['uid', 'access_token', 'refresh_token', 'username', 'display_name', 'account_id', 'uuid']);
        $query->values([$uid, $token, $refresh_token, $bitbucket_user['username'], $bitbucket_user['display_name'], $bitbucket_user['account_id'], $bitbucket_user['uuid']]);
        $query->execute();
      }
    }
    return TRUE;
  }

  /**
   * check_association().
   * This can only be called as part of the request from Bitbucket. So once we've done
   * the deed, we go to the probo home page.
   */
  public function check_association() {
    $this->associate_user();
    new TrustedRedirectResponse(Url::fromRoute('probo.home')->toString());
  }

  /**
   * get_provider().
   * Get the provider object for use elsewhere.
   * 
   * @param string $redirect_uri
   *   The url that the user will come back to once they have accepted the terms.
   * @return \Stevenmaguire\OAuth2\Client\Provider\Bitbucket $provider
   *   The Bitbucket provider object.
   */
  private function get_provider($redirect_uri) {
    $provider = new \Stevenmaguire\OAuth2\Client\Provider\Bitbucket([
      'clientId' => BITBUCKET_CLIENT_ID,
      'clientSecret' => BITBUCKET_CLIENT_SECRET,
      'redirectUri' => $redirect_uri
    ]);
    return $provider;
  }

  public function select_repositories() {

    // Interface of boxes where you can click go select
    // https://stackoverflow.com/questions/4696198/check-checkbox-when-clicking-on-description
    // Tabs
    // https://www.w3schools.com/howto/howto_js_vertical_tabs.asp

    // Get our Bitbucket object for making queries.
    $bitbucket = $this->bitbucket_authenticate();

    // Get our service information
    $bitbucket_service = $this->get_bitbucket_service();

    // Regardless of the teams this returns, there is also a team with the same name
    // as the username for repositories that are part of no team.
    $teams = $bitbucket->currentUser()->listTeams(['role' => 'admin']);
    $teams['values'][] = [
      'username' => $bitbucket_service->username,
      'display_name' => $bitbucket_service->display_name,
    ];

    foreach ($teams['values'] as $team) {
      $repos = [];
      $proboTeam = new ProboTeam();
      $proboTeam->setName($team['display_name']);
      $proboTeam->setMachineName(probo_machine_name($team['display_name']));
      $page = 1;
      $repositories = $bitbucket->repositories()->users($team['username'])->list(['role' => 'admin']);
      if (empty($repositories['values'])) {
        continue;
      }
      do {
        $page++;
        foreach ($repositories['values'] as $key=> $repository) {
          $repos[$team['username']][$repository['slug']] = $repository;
        }
        $repositories = $bitbucket->repositories()->users($team['username'])->list(['role' => 'admin', 'page' => $page]);
      } while (!empty($repositories['next']));

      $repositories = [];
      foreach ($repos as $team_name => $team_repos) {
        foreach ($team_repos as $repo_name => $repo) {
          $repository = new ProboRepository();
          $uuid = rand(1, 10000);
          $repository->setId($uuid);
          $repository->setName($repo['slug']);
          $project = (!empty($repo['project'])) ? $repo['project'] : "No Project";
          $repository->setProjectName($project);
          $repository->setUrl($repo['links']['html']['href']);
          $repository->setAvatar($repo['links']['avatar']['href']);
          $repositories[] = $repository;
        }
      }
      foreach ($repositories as $repository) {
        $proboTeam->addRepository($repository);
      }
      $proboTeams[] = $proboTeam;
    }

    /*
    $query = \Drupal::database()->select('probo_repositories', 'pr')
      ->fields('pr', ['rid'])
      ->condition('uuid', $repo['uuid'], "=");
    $exists = $query->execute()->fetchAllAssoc('rid');
    $row_class = (!empty($exists)) ? 'enabled' : '';
    */

    return [
      '#theme' => 'probo_select_repositories',
      '#teams' => $proboTeams,
      '#attached' => [
        'library' => [
          'probo/global-styling',
        ],
      ],
    ];
  }

  /**
   * bitbucket_authenticate().
   * Bitbucket tokens only last an hour. When this is up, we need to re-authenticate
   * and get a new token using our refresh token. This function takes care of doing
   * that for us.
   */
  public function bitbucket_authenticate() {
    $redirect_uri = Url::fromRoute('probo.bitbucket_process_code');
    $provider = $this->get_provider($redirect_uri);

    $uid = \Drupal::currentUser()->id();

    $bitbucket_service = $this->get_bitbucket_service();
    $token = $bitbucket_service->access_token;

    $bitbucket = new Client();
    try {
      $bitbucket->authenticate(Client::AUTH_OAUTH_TOKEN, $bitbucket_service->access_token);
      $bitbucket->currentUser()->listTeams(['role' => 'admin']);
    }
    catch (ClientErrorException $e) {
    

      $message = $e->getMessage();
      $status = $e->getCode();

      if ($status == 401) {
        $auth = $provider->getAccessToken('refresh_token', ['refresh_token' => $bitbucket_service->refresh_token]);
        $token = $auth->getToken();
        $refresh_token = $auth->getRefreshToken();
        /** User has already logged into Bitbucket, just update the tokens */
        $query = \Drupal::database()->update('probo_bitbucket');
        $query->condition('uid', $uid, '=');
        $query->fields([
          'access_token' => $token,
          'refresh_token' => $refresh_token,
        ]);
        $query->execute();

        $store = \Drupal::service('tempstore.private')->get('probo');
        $services = $store->get('services');
        $services['bitbucket']->access_token = $token;
        $services['bitbucket']->refresh_token = $refresh_token;
        $store->set('services', $services);
      }
      $bitbucket->authenticate(Client::AUTH_OAUTH_TOKEN, $token);
    }
    return $bitbucket;
  }

  /**
   * get_bitbucket_service().
   * Look at our temporary store (session data) and get whatever we have stored
   * for Bitbucket.
   * 
   * @return array $service
   *   Return the service configuration for Bitbucket if we're authenticated.
   */
  public function get_bitbucket_service() {
    $store = \Drupal::service('tempstore.private')->get('probo');
    if (!empty($store)) {
      $services = $store->get('services');
      if (!empty($services['bitbucket'])) {
        return $services['bitbucket'];
      }
    }
    else {
      $url = $this->get_authorization_code();
      $url->send();
      exit();
    }
  }
}
