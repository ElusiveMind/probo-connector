<?php

namespace Drupal\probo\Controller;

use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Component\Render\FormattableMarkup;
use Symfony\Component\HttpFoundation\RedirectResponse;

use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;

/**
 * Class PBBCGRefreshTokenConfiguration.
 */
class PBBCGRefreshTokenConfiguration extends ControllerBase {

  /**
   * configuration_display.
   *
   * @return array
   *   Given everything that we know, get the configuration from Bitbucket and display it in a raw
   *   YAML configuration form for Probo as well as our docker-compose.yml environment variables.
   */
  public function configuration_display() {
    $code = \Drupal::request()->query->get('code');
    $client = \Drupal::httpClient();
    $cookies = \Drupal::request()->cookies->all();

    if (empty($code)) {
      drupal_set_message('No code provided back by Bitbucket.', 'error');
      return new RedirectResponse(Url::fromRoute('probo.pbbcg_get_client_information')->toString());
    }

    if (empty($cookies['bb_client_key']) || empty($cookies['bb_client_secret'])) {
      drupal_set_message('Client key or client secret are missing. Did you press refresh?', 'error');
      return new RedirectResponse(Url::fromRoute('probo.pbbcg_get_client_information')->toString());
    }

    try {
      $response = $client->request('POST', 'https://bitbucket.org/site/oauth2/access_token', [
        'form_params' => [
          'code' => $code,
          'grant_type' => 'authorization_code',
        ],
        'auth' => [$cookies['bb_client_key'], $cookies['bb_client_secret']],
      ]);
    } catch (ClientException $e) {
      drupal_set_message(Psr7\str($e->getResponse), 'error');
      return new RedirectResponse(Url::fromRoute('probo.pbbcg_get_client_information')->toString());
    }

    // Decode the response from Bitbucket.
    $json = json_decode($response->getBody());

    // Deliver the good news.    
    $bitbucket_configuration  = "bbClientKey: " . $_POST['client_key'] . "\n";
    $bitbucket_configuration .= "bbClientSecret: " . $_POST['client_secret'] . "\n";
    $bitbucket_configuration .= "bbAccessToken: " . $json->access_token . "\n";
    $bitbucket_configuration .= "bbRefreshToken: " . $json->refresh_token;
    include 'includes/present-configuration.inc.php';

    $client_key = $cookies['bb_client_key'];
    $client_secret = $cookies['bb_client_secret'];

    // Clear out the cookies.
    $secure = ($_SERVER['SERVER_PORT'] == 443) ? TRUE : FALSE;
    setrawcookie('bb_client_key', '', -1, '/', $_SERVER['SERVER_NAME'], $secure, TRUE);
    setrawcookie('bb_client_secret', '', -1, '/', $_SERVER['SERVER_NAME'], $secure, TRUE);

    return [
      '#theme' => 'probo_configuration_display',
      '#client_key' => $client_key,
      '#client_secret' => $client_secret,
      '#access_token' => $json->access_token,
      '#refresh_token' => $json->refresh_token,
    ];
  }
}