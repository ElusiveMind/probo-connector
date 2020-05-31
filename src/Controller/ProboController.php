<?php

namespace Drupal\probo\Controller;

use Drupal\Core\Url;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class ProboController.
 */
class ProboController extends ControllerBase {

  /**
   * build_details($build_id).
   * Get the details of the build including a list of all the tasks
   * associated with that build.
   *
   * @param int
   *   The build id for the build that we are displaying the details of.
   */
  public function build_details($bid) {
    $config = $this->config('probo.probosettings');

    // Get the builds from our database.
    $query = \Drupal::database()->select('probo_builds', 'pb')
      ->fields('pb', ['id', 'bid', 'repository', 'owner', 'service', 'pull_request_name', 
        'author_name', 'pull_request_url'])
      ->condition('bid', $bid);
    $build = $query->execute()->fetchAllAssoc('id');
    $build = array_pop($build);

    $build_info = [
      'bid' => $build->bid,
      'repository' => $build->repository,
      'owner' => $build->owner,
      'pull_request_name' => $build->pull_request_name,
      'author_name' => $build->author_name,
      'pull_request_url' => $build->pull_request_url,
      'service' => $build->service,
      'build_url' => $config->get('probo_builds_protocol') . '://' . $build->bid . '.' . $config->get('probo_builds_domain'),
    ];

    // Get the builds from our database.
    $query = \Drupal::database()->select('probo_tasks', 'pb')
      ->fields('pb', ['bid', 'tid', 'event_name', 'plugin', 'timestamp', 'payload', 'state'])
      ->condition('bid', $bid)
      ->orderBy('tid', 'ASC');
    $objects = $query->execute()->fetchAllAssoc('tid');

    $previous_start_time = 0;
    $tasks = [];
    foreach ($objects as $object) {
      if ($previous_start_time == 0) {
        $previous_start_time = $object->timestamp;
        $duration = NULL;
      }
      else {
        $duration = number_format($object->timestamp - $previous_start_time, 3) . ' seconds';
        $previous_start_time = $object->timestamp;
      }

      switch ($object->state) {
        case 'success':
          $statusIcon = "fas fa-check-circle";
          $statusColor = "probo-text-green";
          break;
        case 'pending':
          $statusIcon = "fas fa-hourglass-half";
          $statusColor = "probo-text-blue";
          break;
        case 'running':
        case 'inprogress':
          $statusIcon = "fas fa-running";
          $statusColor = "probo-text-blue";
          break;
        case 'failure':
          $statusIcon = "fas fa-times-circle";
          $statusColor = "probo-text-dark";
          break;
        default:
          $statusIcon = "fas fa-minus-circle";
          $statusColor = "probo-text-dark";
          break;
      }

      $tasks[] = [
        'tid' => $object->tid,
        'event_name' => $object->event_name,
        'plugin' => $object->plugin,
        'date' => date('m/d/Y H:i:s', (int)$object->timestamp),
        'duration' => $duration,
        'details' => $object->payload,
        'status_icon' => $statusIcon,
        'status_color' => $statusColor,
      ];
    }

    return [
      '#theme' => 'probo_build_details', 
      '#build' => $build_info,
      '#tasks' => $tasks,
      '#attached' => [
        'library' => [
          'proboci/accordion',
        ],
      ],
    ];    
  }

  /**
   * service_endpoint().
   * A replacement for process_probo_build which required an event for the build to show up
   * in our module directory.
   */
  public function service_endpoint( Request $request ) {
    $config = $this->config('probo.probosettings');

    // Get the input from our posted data. If no data was posted, then we can
    // bail on the operation.
    $data = json_decode($request->getContent(), FALSE);

    if (!empty($data)) {
      $repository_id = $this->get_repository_id($data->owner, $data->repository);
      if ($repository_id == 0) {
        $response = [
          'data' => 'Failure: Repository Not Found',
          'method' => 'GET'
        ];
        header('Access-Control-Allow-Origin: *');
        return new JsonResponse($response);
      }

      $build_id = $data->build_id;
      $repository = $data->repository;
      $owner = $data->owner;
      $service = $data->service;
      $pull_request_url = $data->pull_request_url;
      $pull_request_name = $data->pull_request_name;
      $author_name = $data->author_name;
      $task_id = $data->task_id;
      $task_name = $data->task_name;
      $task_plugin = $data->task_plugin;
      $task_description = $data->task_description;
      $task_context = $data->task_context;
      $task_state = $data->task_state;
      $task_time = microtime(TRUE);

      $stream_code = 'build-' . $build_id . '-task-' . $task_id;
      $task_payload = $this->get_loom_stream($build_id, $task_id, $stream_code);

      // Store our build data in the build table.
      \Drupal::database()->merge('probo_builds')
        ->key(['bid' => $build_id])
        ->insertFields(['bid' => $build_id, 'rid' => $repository_id, 'owner' => $owner, 'repository' => $repository, 'service' => $service,
          'pull_request_name' => $pull_request_name, 'author_name' => $author_name, 'pull_request_url' => $pull_request_url])
        ->updateFields(['owner' => $owner, 'repository' => $repository, 'service' => $service, 'pull_request_name' => $pull_request_name, 
         'author_name' => $author_name, 'pull_request_url' => $pull_request_url])
        ->execute();

      // Store our individual task data in the task table.
      \Drupal::database()->merge('probo_tasks')
        ->key(['bid' => $build_id, 'tid' => $task_id])
        ->insertFields(['bid' => $build_id, 'rid' => $repository_id, 'tid' => $task_id, 'timestamp' => (string) $task_time, 'state' => $task_state,
          'event_name' => $task_name, 'event_description' => $task_description, 'plugin' => $task_plugin, 'context' => $task_context,
          'payload' => $task_payload])
        ->updateFields(['timestamp' => (string) $task_time, 'state' => $task_state, 'event_name' => $task_name, 
          'event_description' => $task_description, 'plugin' => $task_plugin, 'context' => $task_context, 'payload' => $task_payload])
        ->execute();

      // Find out if all of the tasks for this build are successful. If so, post to JIRA - but be sure to do so only once
      $query = \Drupal::database()->select('probo_tasks', 'pt')
        ->fields('pt', ['bid', 'tid', 'state'])
        ->condition('bid', $build_id, '=')
        ->execute();
      $all_success = TRUE;
      while ($tasks = $query->fetchObject()) {
        if ($tasks->state != 'success') {
          $all_success = FALSE;
        }
      }

      if ($all_success === TRUE) {
        preg_match_all("/\\[(.*?)\\]/", $pull_request_name, $matches); 
        if (is_array($matches)) {
          foreach ($matches[1] as $issue_label) {
            $query = \Drupal::database()->select('probo_jira_comments', 'jc')
              ->condition('issue_label', $issue_label, '=')
              ->condition('bid', $build_id, '=');
            $query->addExpression('COUNT(*)');
            $count = $query->execute()->fetchField();
            if ($count < 1) {
              $this->post_to_jira($issue_label, $build_id, $pull_request_name, $pull_request_url);
            }
          }
        }
      }

      $response = [
        'data' => 'Success',
        'method' => 'GET'
      ];
    }
    else {
      $response = [
        'data' => 'Failure',
        'method' => 'GET'
      ];
    }
    header('Access-Control-Allow-Origin: *');
    return new JsonResponse($response);
  }

  /**
   * get_repository_id()
   *
   * Get the repository ID based on the name.
   *
   *  @param string $owner
   *    The string containing the owner of the repository. This is the machine name.
   *
   *  @param string $repository
   *    The repository we want to get the id for.
   *
   *  @return int $rid
   *    The repository id of the queried repository.
   */
  private function get_repository_id($owner, $repository) {
    $query = \Drupal::database()->select('probo_repositories', 'pr')
      ->fields('pr', ['rid'])
      ->condition('owner', $owner, '=')
      ->condition('repository', $repository, '=')
      ->condition('active', 1, '=')
      ->execute();
      $result = $query->fetchObject();
      if (empty($result) || empty($result->rid) || (int) $result->rid == 0) {
        return 0;
      }
      return $result->rid;
  }

  /**
   * get_loom_stream()
   * 
   * @param string $build_id
   *   The id of the build to get the details of the task
   * @param string $task_id
   *   The id of the task to get the details for.
   * @param string $stream_code
   *   The stream code stored in the loom made up of build and task id
   * @return string
   *   The data from probo-loom
   */
  private function get_loom_stream($build_id, $task_id, $stream_code) {
    $config = $this->config('probo.probosettings');

    $loom_stream_url = $config->get('probo_loom_stream_url') . '/stream/' . $stream_code;
    $loom_stream_token = $config->get('probo_loom_stream_token');
    $options = array(
      'http' => array(
        'header' => array(
          'Authorization: Bearer ' . $loom_stream_token,
        ),
        'method' => 'GET'
      )
    );
    $context = stream_context_create($options);
    $result = file_get_contents($loom_stream_url, false, $context);
    return $result;
  }

  /**
   * repository_status($repository_id, $token).
   * A json feed to provide a list of builds within a repository.
   *
   * @param string $repository_id
   *   The id of the repository to get the details of the repository.
   * @param string $token
   *   The API token submitted with the request.
   * @return string $json
   *   The json version of the builds array for the requesting app.
   */
  public function repository_status($repository_id, $token) {
    $config = $this->config('probo.probosettings');
    $config_token = $config->get('probo_api_token');
    $check = $this->check_tokens($token, $config_token);
    if ($check !== TRUE) {
      return $check;
    }
    $query = \Drupal::database()->select('probo_repositories', 'pr')
      ->fields('pr', ['owner', 'repository'])
      ->condition('rid', $repository_id, '=')
      ->execute();
    $repo = $query->fetchObject();
    $repository_name = $repo->owner . ' - ' . $repo->repository;

    // Create the JSON feed for the API as part of our ReactJS interface
    // Get the build data for the overall build before we get the task specific information for each task
    // in the build.
    $query = \Drupal::database()->select('probo_builds', 'pb')
      ->fields('pb', ['id', 'rid', 'bid', 'owner', 'repository', 'service', 'pull_request_name', 'author_name', 'pull_request_url'])
      ->condition('rid', $repository_id, '=')
      ->orderBy('bid', 'ASC')
      ->execute();
  
    $builds = $all = [];
    $builds['builds'] = [];
    while ($repository = $query->fetchObject()) {
      $build = [];
      $build['rid'] = $repository->rid;
      $build['buildID'] = $repository->bid;
      $build['pullRequestName'] = $repository->pull_request_name . ' - ' . $repository->author_name;
      $build['URL'] = $config->get('probo_builds_protocol') . '://' . $repository->bid . '.' . $config->get('probo_builds_domain');
      $build['pullRequestURL'] = $repository->pull_request_url;
      
      $query2 = \Drupal::database()->select('probo_tasks', 'pt')
        ->fields('pt', ['bid', 'rid', 'tid', 'state', 'event_name', 'event_description', 'plugin', 'context', 'payload'])
        ->condition('bid', $repository->bid, '=')
        ->orderBy('bid', 'ASC')
        ->orderBy('tid', 'ASC')
        ->execute();

      while($tasks = $query2->fetchObject()) {
        switch ($tasks->state) {
          case 'success':
            $statusIcon = "fas fa-check-circle";
            $statusColor = "probo-text-green";
            break;
          case 'pending':
            $statusIcon = "fas fa-hourglass-half";
            $statusColor = "probo-text-blue";
            break;
          case 'running':
          case 'inprogress':
            $statusIcon = "fas fa-running";
            $statusColor = "probo-text-blue";
            break;
          case 'failure':
            $statusIcon = "fas fa-times-circle";
            $statusColor = "probo-text-dark";
            break;
          default:
            $statusIcon = "fas fa-minus-circle";
            $statusColor = "probo-text-dark";
            break;
        }
        $build['steps'][]  = [
          'statusIcon' => $statusIcon,
          'statusColor' => $statusColor,
          'statusTask' => $tasks->tid,
          'statusEventName' => $tasks->event_name,
        ];
      }
      $all['builds'][] = $build;
    }
    $all['repositoryName'] = $repository_name;
    header('Access-Control-Allow-Origin: *');
    return new JsonResponse($all);
  }

  /**
   * specific_build_status($build_id, $token).
   * A json feed to provide details of a specific build.
   *
   * @param string $build_id
   *   The id of the build to get the details.
   * @param string $token
   *   The API token submitted with the request.
   * @return string $json
   *   The json version of the builds array for the requesting app.
   */
  public function specific_build_status($build_id, $token) {
    $config = $this->config('probo.probosettings');
    $config_token = $config->get('probo_api_token');
    $build_domain = $config->get('probo_builds_domain');
    $protocol = $config->get('probo_builds_protocol');

    $check = $this->check_tokens($token, $config_token);
    if ($check !== TRUE) {
      return $check;
    }

    $query = \Drupal::database()->select('probo_tasks', 'pt')
      ->fields('pt', ['bid', 'rid', 'tid', 'state', 'event_name', 'event_description', 'plugin', 'context', 'payload'])
      ->condition('bid', $build_id, '=')
      ->orderBy('bid', 'ASC')
      ->orderBy('tid', 'ASC')
      ->execute();

    $build = [];
    $ready = 0;
    while($tasks = $query->fetchObject()) {
      switch ($tasks->state) {
        case 'success':
          $statusIcon = "fas fa-check-circle";
          $statusColor = "probo-text-green";
          $ready = 1;
          break;
        case 'pending':
          $statusIcon = "fas fa-hourglass-half";
          $statusColor = "probo-text-blue";
          $ready = 0;
          break;
        case 'running':
        case 'inprogress':
          $statusIcon = "fas fa-running";
          $statusColor = "probo-text-blue";
          $ready = 0;
          break;
        case 'failure':
          $statusIcon = "fas fa-times-circle";
          $statusColor = "probo-text-dark";
          $ready = 0;
          break;
        default:
          $statusIcon = "fas fa-minus-circle";
          $statusColor = "probo-text-dark";
          $ready = 0;
          break;
      }
      $build['steps'][]  = [
        'statusIcon' => $statusIcon,
        'statusColor' => $statusColor,
        'statusTask' => $tasks->tid,
        'statusEventName' => $tasks->event_name,
      ];
    }
    $build['ready'] = $ready;
    $build['url'] = $protocol . '://' . $build_id . '.' . $build_domain;
    $build['id'] = $build_id;
    header('Access-Control-Allow-Origin: *');
    return new JsonResponse($build);
  }

  /**
    * check_token($token, $config_token).
    * A json feed to provide the status of each step in the build process.
    *
    * @param string $token
    *   The token submitted with the request.
    * @param string $config_token
    *   The accepted token configured on the settings page.
    * @return string/bool
    *   Returns FALSE if not successful, otherwise a json response.
    */
  private function check_tokens($token, $config_token) {
    if (empty($config_token) || $config_token == '') {
      $error = [
        'errorCode' => 403,
        'error' => 'Your have not assigned a token for API requests. Please assign a token and send it in your request.',
      ];
      return $error;
    }
    if (empty($token) || $token != $config_token) {
      $error = [
        'errorCode' => 404,
        'error' => 'You have not supplied a valid token with your request. Please assign your token to your request and try again.',
      ];
      return $error;
    }
    return TRUE;
  }

  /**
   * build_error_screen().
   * Display an error screen for the probo-proxy redirect.
   */
  public function build_error_screen() {
    $config = \Drupal::configFactory()->getEditable('probo.probosettings');
    $build_domain = $config->get('probo_builds_domain');
    $protocol = $config->get('probo_builds_protocol');

    $module_handler = \Drupal::service('module_handler');
    $path = $module_handler->getModule('probo')->getPath();
    $document_root = $_SERVER['DOCUMENT_ROOT'];
    $module_image_path = '/' . str_replace($document_root, '', $path) . '/images';

    return [
      '#theme' => 'probo_build_error',
      '#build_id' => \Drupal::request()->query->get('buildId'),
      '#error_code' => \Drupal::request()->query->get('errorCode'),
      '#reason' => \Drupal::request()->query->get('reason'),
      '#images' => $module_image_path,
      '#protocol' => $protocol,
      '#build_domain' => $build_domain,
    ];
  }

  /**
   * solr()
   * Given a build id, redirect the user to the mailcatcher instance for their build. Fetches the port
   * of mailcatcher and appends it to a non-ssl domain.
   *
   * @param string build_id
   *   The build id of the build we need to redirect to.
   */
  public function solr($bid) {
    $config = \Drupal::configFactory()->getEditable('probo.probosettings');
    $mailcatcher = $config->get('probo_solr_domain');
    $protocol = $config->get('probo_builds_protocol', 'http');

    $container_name = $this->get_container_name($bid);
    $output = [];
    exec('docker port ' . $container_name, $output);
    foreach($output as $line) {
      // Get the port of whatever is on 8983... that is our mailcatcher proxy.
      // example: 8983/tcp -> 0.0.0.0:32914
      list($mail_port, $rest) = explode('/', $line);
      if ($mail_port == '8983') {
        list($rest, $container_port) = explode(':', $rest);
        $url = $protocol . '://' . $container_port . '.' . $mailcatcher;
        header('location: ' . $url);
        exit();
      }
    }
    // Fallback is to take them to the build. This may mean the image does not have a mailcatcher
    // instance (14.04 builds are guilty of this due to Ruby versioning)
    header('location: https://' . $bid . '.' . $build_domain);
    exit();
  }

  /**
   * mailcatcher()
   * Given a build id, redirect the user to the mailcatcher instance for their build. Fetches the port
   * of mailcatcher and appends it to a non-ssl domain.
   *
   * @param string build_id
   *   The build id of the build we need to redirect to.
   */
  public function mailcatcher($bid) {
    $config = \Drupal::configFactory()->getEditable('probo.probosettings');
    $mailcatcher = $config->get('probo_mailcatcher_domain');
    $protocol = $config->get('probo_builds_protocol', 'http');

    $container_name = $this->get_container_name($bid);
    $output = [];
    exec('docker port ' . $container_name, $output);
    foreach($output as $line) {
      // Get the port of whatever is on 1080... that is our mailcatcher proxy.
      // example: 1080/tcp -> 0.0.0.0:32914
      list($mail_port, $rest) = explode('/', $line);
      if ($mail_port == '1080') {
        list($rest, $container_port) = explode(':', $rest);
        $url = $protocol . '://' . $container_port . '.' . $mailcatcher;
        header('location: ' . $url);
        exit();
      }
    }
    // Fallback is to take them to the build. This may mean the image does not have a mailcatcher
    // instance (14.04 builds are guilty of this due to Ruby versioning)
    header('location: https://' . $bid . '.' . $build_domain);
    exit();
  }

  /**
   * uli()
   * Run a drush uli and go to the url.
   *
   * @param string build_id
   *   The build id of the build we need to redirect to.
   */
  public function uli($bid) {
    $this->uublk($bid);
    $config = \Drupal::configFactory()->getEditable('probo.probosettings');
    $url = $config->get('probo_builds_protocol') . '://' . $bid . '.' . $config->get('probo_builds_domain');
    $container_name = $this->get_container_name($bid);
    $output = [];
    exec('docker exec ' . $container_name . ' drush -r /var/www/html uli -l ' . $url, $output);
    header('location: ' . $output[0]);
    exit();
  }


  /**
   * remove()
   * Remove a script from the interface. We will also want to reap this build. Or at least make sure
   * it is reaped
   *
   * @param string build_id
   *   The build id of the build we need to remove.
   */
  public function remove($bid) {
    //$config = \Drupal::configFactory()->getEditable('probo.probosettings');
    //$url = $config->get('probo_builds_protocol') . '://' . $bid . '.' . $config->get('probo_builds_domain');
    //$container_name = $this->get_container_name($bid);
    //$output = [];
    //exec('docker exec ' . $container_name . ' drush -r /var/www/html uublk 1 -l ' . $url, $output);

    // Remove the build from the database.
    $query = \Drupal::database()->delete('probo_builds')
      ->condition('bid', $bid)
      ->execute();
    $query = \Drupal::database()->delete('probo_tasks')
      ->condition('bid', $bid)
      ->execute();
    remove_from_jira($bid);
  }

  /**
   * uublk()
   * Run a drush uublk to unblock user 1 as a precaution and then return success/failure
   *
   * @param string build_id
   *   The build id of the build we need to redirect to.
   */
  public function uublk($bid) {
    $config = \Drupal::configFactory()->getEditable('probo.probosettings');
    $url = $config->get('probo_builds_protocol') . '://' . $bid . '.' . $config->get('probo_builds_domain');
    $container_name = $this->get_container_name($bid);
    $output = [];
    exec('docker exec ' . $container_name . ' drush -r /var/www/html uublk 1 -l ' . $url, $output);
  }

  /**
   * get_container_size()
   * Get the amount of disc space consumed by a particular container.
   *
   * @param string build_id
   *   The build id of the build we need the size of.
   */
  public function get_container_size($bid) {
    $container_name = $this->get_container_name($bid);
    $output = [];
    exec('docker ps -s -f name=' . $container_name, $output);
    foreach($output as $key => $line) {
      if ($key == 0) {
        continue;
      }
      $location = strpos($line, $container_name);
      if ($location !== FALSE) {
        $start = $location + strlen($container_name);
        $string_to_search = substr($line, $start);
        $parts = explode('(', $string_to_search);
        $value = trim($parts[0]);

        $unit = substr($value, -2);
        $size = substr($value, 0, -2);
        $size = trim($size);

        switch ($unit) {
          case 'kB':
            $size *= 1024;
            break;
          case 'MB':
            $size *= (1024 * 1024);
            break;
          case 'GB':
            $size *= (1024 * 1024 * 1024);
            break;
          default:
            break;
        }
        print $size;
      }
    }
    exit();
  }

  /**
   * get_container_name()
   * Calculate the docker name of the container given the build id.
   *
   * @param string build_id
   *   The build id of the build we need to calculate the docker name of.
   */
  public function get_container_name($bid) {
    $config = \Drupal::configFactory()->getEditable('probo.probosettings');
    $build_domain = $config->get('probo_builds_domain');

    $query = \Drupal::database()->select('probo_builds', 'pb')
      ->fields('pb', ['id', 'rid', 'bid', 'owner', 'repository', 'service', 'pull_request_name', 'author_name', 'pull_request_url'])
      ->condition('bid', $bid, '=')
      ->execute();

    // Construct the image name to retrieve it from docker.
    // example: probo--fsisap.fsis_analytics--fsisap-fsis_analytics--5bd323b6-1ed6-496b-a6c2-79bced0dc173
    $repository = $query->fetchObject();
    $owner = $repository->owner;
    $repo = $repository->repository;
    $container_name = 'probo--' . $owner . '.' . $repo . '--' . $owner . '-' . $repo . '--' . $bid;
    return $container_name;
  }

  /**
   * post_to_jira()
   * Post a status message to JIRA about the pull request and build..
   *
   * @param string issue_label
   *   The label provided in the pull request name.
   * 
   * @param string build_id
   *   The build id as assigned by Probo.
   * 
   * @param string pull_request_name
   *   The name (title) of the pull request.
   * 
   * @param string pull_request_url
   *   The URL to the pull request.
   */
  private function post_to_jira($issue_label, $build_id, $pull_request_name, $pull_request_url) {
    $config = $this->config('probo.probosettings');
    $jira_url = $config->get('jira_url');
    $jira_api_username = $config->get('jira_api_username');
    $jira_api_token = $config->get('jira_api_token');
    $jira_enabled = $config->get('jira_enabled');
    $domain = $config->get('probo_builds_domain');
    $protocol = $config->get('probo_builds_protocol');

    // If JIRA isn't configured, then just go back.
    if (empty($jira_api_username) || empty($jira_api_token) || empty($jira_enabled)) {
      return FALSE;
    }

    $build_url = $protocol . '://' . $build_id . '.' . $domain;
    $url = $jira_url . '/rest/api/2/issue/' . $issue_label . '/comment';

    $message = [];
    $message['body'] = "A new pull request and Probo build associated with this issue has been created.\n\n$pull_request_name\n$pull_request_url\n$build_url";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
      'Content-Type: application/json',
      'Authorization: Basic ' . base64_encode($jira_api_username.':'.$jira_api_token),
      'Content-Length: ' . strlen(json_encode($message))
    ));
    curl_setopt($ch, CURLOPT_USERPWD, "$jira_api_username:$jira_api_token");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response);
    if ($data !== NULL) {
      $comment_id = $data->id;
      $parts = explode('/', $data->self);
      $issue_id = $parts[7];
      $query = \Drupal::database()->insert('probo_jira_comments')
        ->fields([
          'issue_label' => $issue_label,
          'bid' => $build_id,
          'issue_id' => $issue_id,
          'comment_id' => $comment_id,
        ])
        ->execute();
    }
  }

}

