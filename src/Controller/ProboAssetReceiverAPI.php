<?php

namespace Drupal\probo\Controller;

use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Component\Render\FormattableMarkup; 

define('PROBO_UPLOADS_UNPAUSED', 1);
define('PROBO_UPLOADS_PAUSED', 0);

/**
 * Class ProboAssetReceiverAPI.
 */
class ProboAssetReceiverAPI extends ControllerBase {

  /**
   * getBuckets().
   * Get all bucket data available from the asset receiver.
   * 
   * app.get('/buckets', auth, this.routes.listBuckets);
   * 
   * @return array $response
   *   The decoded JSON from the asset receiver with all bucket data.
   */
  public function getBuckets() {
    $data_export = $options = [];
    $config = $this->config('probo.asset_receiver');
    $options['header'] = $this->getBearerToken();
    $url = $config->get('url') . '/buckets';
    $response = $this->assetReceiverRequest('GET', $url, $options);
    return json_decode($response['body']);
  }

  /**
   * getBucket().
   * Get all bucket data for the bucket specified.
   * 
   * app.get('/buckets/:bucket', auth, this.routes.getBucket);
   * 
   * @param string $bucket
   *   The name of the bucket to fetch information for.
   * @return array $response
   *   The decoded JSON from the asset receiver with all specific bucket data.
   */
  public function getBucket(string $bucket) {
    $data_export = $options = [];
    $config = $this->config('probo.asset_receiver');
    $options['header'] = $this->getBearerToken();
    $url = $config->get('url') . '/buckets/' . $bucket;
    $response = $this->assetReceiverRequest('GET', $url, $options);
    if ($response['code'] != 200) {
      \Drupal::logger('probo')->error('Unable to get asset receiver bucket "' . $bucket . '" - Error Code: ' . $response['code']);
      $response['body'] = ['text' => $response['body']];
      return $response;
    }
    return json_decode($response['body']);
  }

  /**
   * createBucket().
   * Get all bucket data for the bucket specified.
   * app.post('/buckets/:bucket', auth, bodyParser.json(), this.routes.createBucket);
   * 
   * @param string $bucket
   *   The name of the bucket to fetch information for.
   * @param array $metadata
   *   An associative array of metadata to add to the bucket.
   * @return array $response
   *   The decoded JSON from the asset receiver with all result data.
   */
  public function createBucket(string $bucket, array $metadata = []) {
    $config = $this->config('probo.asset_receiver');
    $options['header'] = $this->getBearerToken();
    $options['json'] = $metadata;
    $url = $config->get('url') . '/buckets/' . $bucket;
    $response = $this->assetReceiverRequest('POST', $url, $options);
    if ($response['code'] != 201) {
      \Drupal::logger('probo')->error('Unable to set asset receiver bucket "' . $bucket . '" - Error Code: ' . $response['code']);
      $response['body'] = ['text' => $response['body']];
      return $response;
    }
    return json_decode($response['body']);
  }

  /**
   * createBucketToken().
   * Create a file access token for the provided bucket. Note this requires you
   * to provide the token. It will not create one if it is not provided.
   * 
   * app.post('/buckets/:bucket/token/:token', auth, this.routes.createBucketToken);
   * 
   * @param string $bucket
   *   The name of the bucket to create the token for.
   * @param array $token
   *   The token. Required. This must be provided.
   * @return array $response
   *   The decoded JSON from the asset receiver with all result data.
   */
  public function createBucketToken(string $bucket, string $token) {
    $config = $this->config('probo.asset_receiver');
    $options['header'] = $this->getBearerToken();
    $url = $config->get('url') . '/buckets/' . $bucket . '/token/' . $token;
    $response = $this->assetReceiverRequest('POST', $url, $options);
    if ($response['code'] != 201) {
      \Drupal::logger('probo')->error('Unable to set bucket token for the bucket "' . $bucket . '" - Error Code: ' . $response['code']);
      $response['body'] = ['text' => $response['body']];
      return $response;
    }
    return json_decode($response['body']);
  }
  /**
   * deleteBucketToken().
   * Delete a file access token for the provided bucket. Note this requires you
   * to provide the token.
   * 
   * app.delete('/buckets/:bucket/token/:token', auth, this.routes.deleteBucketToken);
   * 
   * @param string $bucket
   *   The name of the bucket to create the token for.
   * @param array $token
   *   The token to delete.
   * @return array $response
   *   The decoded JSON from the asset receiver with all result data.
   */
  public function deleteBucketToken(string $bucket, string $token) {
    $config = $this->config('probo.asset_receiver');
    $options['header'] = $this->getBearerToken();
    $url = $config->get('url') . '/buckets/' . $bucket . '/token/' . $token;
    $response = $this->assetReceiverRequest('DELETE', $url, $options);
    if ($response['code'] != 202) {
      \Drupal::logger('probo')->error('Unable to delete bucket token "' . $token . '" for the "' . $bucket . '" bucket. - Error Code: ' . $response['code']);
      $response['body'] = ['text' => $response['body']];
      return $response;
    }
    return json_decode($response['body']);
  }

  /**
   * listBucketTokens().
   * Get all bucket data for the bucket specified.
   * 
   * app.get('/buckets/:bucket/token', auth, this.routes.listBucketTokens);
   * 
   * @param string $bucket
   *   The name of the bucket to fetch tokens for.
   * @return array $response
   *   The decoded JSON from the asset receiver with all specific data.
   */
  public function listBucketTokens($bucket) {
    $config = $this->config('probo.asset_receiver');
    $options['header'] = $this->getBearerToken();
    $url = $config->get('url') . '/buckets/' . $bucket . '/token';
    $response = $this->assetReceiverRequest('GET', $url, $options);
    if ($response['code'] != 200) {
      \Drupal::logger('probo')->error('Unable to get tokens "' . $token . '" for the "' . $bucket . '" bucket. - Error Code: ' . $response['code']);
      $response['body'] = ['text' => $response['body']];
      return $response;
    }
    return json_decode($response['body']);
  }

  /**
   * receiveFileAsset().
   * Receive uploaded, binary file data.
   * 
   * app.post('/asset/:token/:assetName', this.routes.receiveFileAsset);
   * 
   * @param string $token
   *   The upload token to which this upload belongs.
   * @param string $assetName
   *   The filename of the uploaded asset.
   * @param string $assetData
   *   The binary data to be associated with the asset.
   */
  public function receiveFileAsset($token, $assetName, $assetData) {
    $config = $this->config('probo.asset_receiver');
    $url = $config->get('url') . '/asset/' . $token . '/' . $assetName;
    $params = [
      'timeout' => 300,
      'body' => $assetData,
    ];
    $response = $this->assetReceiverRequest('POST', $url, $params);
    if ($response['code'] != 201) {
      \Drupal::logger('probo')->error('Unable to upload asset to upload token "' . $token . '". - Error Code: ' . $response['code']);
      $response['body'] = ['text' => $response['body']];
    }
    return $response;
  }

  /**
   * serveFileAsset().
   * A function to retrieve the file from the file store and present it.
   * 
   * app.get('/asset/:bucket/:assetName', auth, this.routes.serveFileAsset);
   * 
   * @param string $token
   *   The upload token to which this upload belongs.
   * @param string $assetName
   *   The filename of the uploaded asset.
   * @return string $url
   *   The url to serve the asset.
   */
  public function serveFileAsset($bucket, $assetName) {
    $config = $this->config('probo.asset_receiver');
    $url = $config->get('url') . '/asset/' . $bucket . '/' . $assetName;
    return $url;
  }

  /**
   * listAssetInfo().
   * Get the metadata (in bytes) of the asset.
   * 
   * app.get('/buckets/:bucket/assets/:assetName/size', auth, this.routes.listAssetSize);
   * 
   * @param string $bucket
   *   The bucket name from which we are getting the asset info.
   * @param string $asset_name
   *   The name of the asset to be retrieved (filename).
   * @return array $response
   *   The response.
   */
  public function listAssetInfo($bucket, $assetName) {
    $config = $this->config('probo.asset_receiver');
    $url = $config->get('url') . '/buckets/' . $bucket . '/assets/' . $assetName . '/size';
    $options['header'] = $this->getBearerToken();
    $response = $this->assetReceiverRequest('GET', $url, $options);
    if ($response['code'] != 200) {
      \Drupal::logger('probo')->error('Unable to get asset information for "' . $bucket . '/' . $assetName.'". - Error Code: ' . $response['code']);
      $response['body'] = ['body' => $response['body']];
    }
    return json_decode($response['body']);
  }

  /**
   * assetMetadataByBucket().
   * Get the assets in a bucket and the metadata associated with it.
   * 
   * app.get('/buckets/:bucket/assets', auth, this.routes.assetMetadataByBucket);
   * 
   * @param string $bucket
   *   The bucket for which we are getting the asset metadata.
   * @return array $metadata
   *   An array of the assets in the bucket.
   */
  public function assetMetadataByBucket($bucket) {
    $config = $this->config('probo.asset_receiver');
    $url = $config->get('url') . '/buckets/' . $bucket . '/assets';
    $options['header'] = $this->getBearerToken();
    $response = $this->assetReceiverRequest('GET', $url, $options);
    if ($response['code'] != 200) {
      \Drupal::logger('probo')->error('Unable to get asset information for "' . $bucket . '/' . $assetName.'". - Error Code: ' . $response['code']);
      $response['body'] = ['body' => $response['body']];
    }
    return json_decode($response['body']);
  }

  /**
   * deleteAssetFromBucket().
   * Delete the asset from a bucket (stack style).
   * 
   * app.delete('/buckets/:bucket/assets/:assetName', auth, this.routes.deleteAssetFromBucket);
   * 
   * @param string $bucket
   *   The bucket to which this upload belongs.
   * @param string $assetName
   *   The filename of the asset.
   * @return array $response
   *   The response.
   */
  public function deleteAssetFromBucket($bucket, $assetName) {
    $config = $this->config('probo.asset_receiver');
    $url = $config->get('url') . '/buckets/' . $bucket . '/assets/' . $assetName;
    $options['header'] = $this->getBearerToken();
    $response = $this->assetReceiverRequest('DELETE', $url, $options);
    if ($response['code'] != 202) {
      \Drupal::logger('probo')->error('Unable to get delete asset from "' . $bucket . '/' . $assetName.'". - Error Code: ' . $response['code']);
      $response['body'] = ['body' => $response['body']];
    }
    return json_decode($response['body']);
  }

  /**
   * setUploadStatus().
   * Sets the upload status for the asset receiver.
   * 
   * app.post('/service/upload-status', auth, bodyParser.json(), this.routes.uploadStatus);
   * 
   * @param int $uploadStatus
   *   Sets to 1 to resume uploads, 0 to pause uploads.
   */
  public function setUploadStatus($uploadStatus) {
    $config = $this->config('probo.asset_receiver');
    $options['header'] = $this->getBearerToken();
    $options['json'] = [
      'uploadsPaused' => !$uploadStatus,
    ];
    $url = $config->get('url') . '/service/upload-status';
    $response = $this->assetReceiverRequest('POST', $url, $options);
    if ($response['code'] != 201) {
      \Drupal::logger('probo')->error('Unable to set upload status for the asset receiver. - Error Code: ' . $response['code']);
    }
  }

  /**
   * getUploadStatus().
   * Get the current upload status.
   * 
   * app.get('/service/upload-status', auth, this.routes.getUploadStatus);
   * 
   * @return int $upload_status
   *   Returns an integer. Returns 1 for unpaused and 0 for paused.
   */
  public function getUploadStatus() {
    $data_export = $options = [];
    $config = $this->config('probo.asset_receiver');
    $options['header'] = $this->getBearerToken();
    $url = $config->get('url') . '/service/upload-status';
    $response = $this->assetReceiverRequest('GET', $url, $options);
    if ($response['body'] == 'Uploads are unpaused.') {
      return PROBO_UPLOADS_UNPAUSED;
    }
    else {
      return PROBO_UPLOADS_PAUSED;
    }
  }

  /**
   * getDataExport().
   * Get a dump of all data in the asset receiver database.
   * 
   * app.get('/service/upload-status', auth, this.routes.getUploadStatus);
   * 
   * @return array $data_export
   *   An array of exported data.
   */
  public function getDataExport() {
    $data_export = $options = [];
    $config = $this->config('probo.asset_receiver');
    $options['header'] = $this->getBearerToken();
    $url = $config->get('url') . '/service/export-data';
    $response = $this->assetReceiverRequest('GET', $url, $options);
    $data = explode("\n", $response['body']);
    foreach ($data as $string) {
      $json = json_decode($string);
      $data_export[] = $json;
    }
    return $data_export;
  }

  /**
   * downloadAsset().
   * Make a call to the asset received daemon and get the file and deliver it to the user.
   *
   * @param string $bucket
   *   The bucket name from which we are getting the asset.
   * @param string $asset_name
   *   The name of the asset to be downloaded (filename).
   * @return RedirectResponse
   *   Redirect to the URL to either download the file or back where we came from.
   */
  public function downloadAsset(string $bucket, string $asset_name) {
    $config = $this->config('probo.asset_receiver');

    /**
     * The bucket name is always the asset owner and the asset repository delimited by a '-'
     * This is part of the envelope we get from list of assets for a bucket.
     */
    $url = $config->get('url') . '/asset/' . $bucket . '/' . $filename;
    $code = $this->assetReceiverRequestCode('GET', $url);

    if ($code == '200') {
      return new TrustedRedirectResponse($url);
    }
    else {
      $error = probo_get_http_error($code);
      \Drupal::messenger()->addError(t('The requested asset could not be downloaded. The returned error was: ' . $error));
    }
  }

  /**
   * assetReceiverRequest().
   * 
   * @param string $method
   *   THe type of request beind made (PUT, GET, DELETE)
   * @param string $url
   *   The url of the asset receiver plus any arguments in the URL. The full URL.
   * @param array $options
   *   An associative array of options to send along with the request.
   * @return array $response
   *   The response from the service.
   *     code - The response code from the request
   *     body - The content returned from the request
   */
  public function assetReceiverRequest(string $method, string $url, array $options) {
    $client = \Drupal::httpClient();
    try {
      $http = $client->request($method, $url, $options);
    }
    catch (ConnectException $e) {
      $msg = $e->getMessage();
      if (strpos($msg, 'Failed to connect')) {
        \Drupal::messenger()->addError(t('Unable to connect to ' . $config->get('url'). ' - please check server or setting'));
        return [
          'body' => NULL,
          'code' => 503,
        ];
      }
    }
    catch (RequestException $e) {
      $response = explode("\n", Psr7\Str($e->getResponse()));
      $code = explode(' ', $response[0]);
      return [
        'body' => $e->getMessage(),
        'code' => $code[1],
      ];
    }
 
    return [
      'body' => $http->getBody()->getContents(),
      'code' => $http->getStatusCode(),
    ];

  }

  /**
   * assetReceiverRequestCode().
   * 
   * @param string $method
   *   THe type of request beind made (PUT, GET, DELETE)
   * @param string $url
   *   The url of the asset receiver plus any arguments in the URL. The full URL.
   * @param array $options
   *   An associative array of options to send along with the request.
   * @return string $status_code
   *   The HTTP status code for the request without getting the body.
   */
  public function assetReceiverRequestCode(string $method, string $url, array $options) {
    $client = \Drupal::httpClient();
    try {
      $http = $client->request($method, $url, $options);
    }
    catch (ConnectException $e) {
      $msg = $e->getMessage();
      if (strpos($msg, 'Failed to connect')) {
        return '503';
      }
    }
    return $http->getStatusCode();
  }

  /**
   * getBearerToken().
   * 
   * Get the bearer token for the asset receiver configured in the administrative control panel.
   * 
   * @return array $token_header
   *   The header to be inserted into the headers option for all guzzle requests
   */
  public function getBearerToken() {
    $config = $this->config('probo.asset_receiver');
    $token = $config->get('token');
    return (!empty($token)) ? ['Authorization' => 'Bearer ' . $token] : [];
  }
}
