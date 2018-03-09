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
    ];

    // Get the builds from our database.
    $query = \Drupal::database()->select('probo_tasks', 'pb')
      ->fields('pb', ['id', 'bid', 'tid', 'event_name', 'plugin', 'timestamp'])
      ->condition('bid', $bid)
      ->orderBy('tid', 'ASC');
    $objects = $query->execute()->fetchAllAssoc('id');

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
      
      $tasks[] = [
        'tid' => $object->tid,
        'event_name' => $object->event_name,
        'plugin' => $object->plugin,
        'date' => date('m/d/Y H:i:s', (int)$object->timestamp),
        'duration' => $duration,
      ];
    }

    return [
      '#theme' => 'probo_build_details', 
      '#build' => $build_info,
      '#tasks' => $tasks,
    ];
    
  }

  /**
   * task_details($task_id).
   *
   * The output of the task in a command line format. This is the general
   * debugging format that is used for checking for build errors.
   *
   * @param int $bid
   *   The id of the build to get the details of the task
   * @param int $tid
   *   The id of the task to get the details for.
   * @return array
   *   The render array for the page of task details.
   */
  public function task_details($bid, $tid) {
    // Get the builds from our database.
    $query = \Drupal::database()->select('probo_tasks', 'pt')
      ->fields('pt', ['id', 'bid', 'payload', 'event_name', 'plugin', 'timestamp'])
      ->condition('bid', $bid)
      ->condition('tid', $tid);
    $task = $query->execute()->fetchAllAssoc('id');
    $task = array_pop($task);

    // Get the builds from our database.
    $query = \Drupal::database()->select('probo_builds', 'pb')
      ->fields('pb', ['id', 'bid', 'repository', 'owner', 'service', 'pull_request_name', 
        'author_name', 'pull_request_url'])
      ->condition('bid', $bid);
    $build = $query->execute()->fetchAllAssoc('id');
    $build = array_pop($build);

    return [
      '#theme' => 'probo_task_details',
      '#build_id' => $bid,
      '#task_id' => $tid,
      '#body' => $task->payload,
      '#event_name' => $task->event_name,
      '#plugin' => $task->plugin,
      '#timestamp' => $task->timestamp,
      '#pull_request_name' => $build->pull_request_name,
      '#pull_request_url' => $build->pull_request_url,
      '#owner' => $build->owner,
      '#repository' => $build->repository,
      '#service' => $build->service,
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
        ->insertFields(['bid' => $build_id, 'owner' => $owner, 'repository' => $repository, 'service' => $service,
          'pull_request_name' => $pull_request_name, 'author_name' => $author_name, 'pull_request_url' => $pull_request_url])
        ->updateFields(['owner' => $owner, 'repository' => $repository, 'service' => $service, 'pull_request_name' => $pull_request_name, 
         'author_name' => $author_name, 'pull_request_url' => $pull_request_url])
        ->execute();
      
      // Store our individual task data in the task table.
      \Drupal::database()->merge('probo_tasks')
        ->key(['bid' => $build_id, 'tid' => $task_id])
        ->insertFields(['bid' => $build_id, 'tid' => $task_id, 'timestamp' => (string) $task_time, 'state' => $task_state,
          'event_name' => $task_name, 'event_description' => $task_description, 'plugin' => $task_plugin, 'context' => $task_context,
          'payload' => $task_payload])
        ->updateFields(['timestamp' => (string) $task_time, 'state' => $task_state, 'event_name' => $task_name, 
          'event_description' => $task_description, 'plugin' => $task_plugin, 'context' => $task_context, 'payload' => $task_payload])
        ->execute();

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
    
    return new JsonResponse($response);
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
   * build_status().
   * A json feed that produces a json response of our build status for all builds in the system.
   * This feed is for our front end Drupal module to update the user interface on build status.
   */
  public function buids_status() {
    // Create the JSON feed for the API as part of our ReactJS interface
    // Get the build data for the overall build before we get the task specific information for each task
    // in the build.
    $query = \Drupal::database()->select('probo_builds', 'db')
      ->fields('pb', ['bid', 'owner', 'repository', 'service', 'pull_request_name', 'author_name', 'pull_request_url'])
      ->orderBy('owner', 'ASC')
      ->orderBy('repository', 'ASC');
    $repositories = $query->execute()->fetchAllAssoc();

    // Get the tasks and the status of each task.
    $query = \Drupal::database()->select('probo_tasks', 'pt')
      ->fields('pt', ['bid', 'tid', 'state', 'event_name', 'event_description', 'plugin', 'context', 'payload'])
      ->orderBy('bid', 'ASC')
      ->orderBy('tid', 'ASC');
    $tasks = $query->execute()->fetchAllAssoc();

    $repositoryName = $reporitory['owner'] . '-' . $repository['repository'];

    $o = new stdClass();
    $builds = [];
    foreach($repositories as $key => $repository) {
      if ($key == 0) {
        $o->repositoryName = $repositoryName;
      }
      $build = new stdClass();
      $build->pullRequestName = $repository['pull_request_name'];
      $build->URL = 'http://' . $repository['bid'] . '.' . $config->get('probo_builds_domain');
      $build->pull_request_url = $repository['pull_request_url'];

      $steps = [];
      $step = new stdClass();
      foreach($tasks as $task) {
        switch ($task['state']) {
          case 1:
            $statusIcon = "fa-check-circle";
            $statusColor = "probo-text-green";
            break;
          case 2:
            $statusIcon = "fa-minus-circle";
            $statusColor = "";
            break;
          case 3:
            $statusIcon = "fa-times-circle";
            $statusColor = "probo-text-dark";
            break;
          default:
            $statusIcon = "fa-minus-circle";
            $statusColor = "";
            break;
        }
        $step->statusIcon = $statusIcon;
        $step->statusColor = $statusColor;
        $steps[] = $step;
      }
      $build->steps = $steps; 
      $builds[] = $build;
    }

    return new JsonResponse($builds);

    //$dir = \Drupal::service('file_system')->realpath(file_default_scheme() . "://");
    //file_put_contents($dir . $repositoryName . '.json', $json);
  }
}