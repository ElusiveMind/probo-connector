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
   * log_listener()
   *
   * Takes the provided input from the script and places it in the database.
   */
  public function log_listener( Request $request ) {
    // Get the input from our posted data. If no data was posted, then we can
    // bail on the operation.
    $data = json_decode($request->getContent(), FALSE);
    
    if (!empty($data)) {
      // Set up local variables to make lives easier.
      $buildId = $data->buildId;
      $taskId = $data->task->id;
      $name = $data->task->name;
      $plugin = $data->task->plugin;
      $body = $data->body;
      $action = $data->action;
      $timestamp = $data->file_time;
    
      // Delete the record from our database if we are specifically told
      // to do so only.
      if ($data->action == 'delete') {
        \Drupal::database()->delete('probo_tasks')
          ->condition('bid', $buildId)
          ->condition('tid', $taskId)
          ->execute();
        
        \Drupal::database()->detete('probo_builds')
          ->condition('bid', $buildId)
          ->execute();
      }
      // Just because we're not deleting doesn't mean we're adding. We may
      // be updating. If we have a record and a file, then do an update.
      // Otherwise, add new.
      elseif ($data->action == 'info') {
        \Drupal::database()->merge('probo_tasks')
          ->key(['bid' => $buildId, 'tid' => $taskId])
          ->insertFields(['bid' => $buildId, 'tid' => $taskId, 'event_name' => $name, 'plugin' => $plugin, 
            'payload' => $body, 'timestamp' => $timestamp])
          ->updateFields(['event_name' => $name, 'plugin' => $plugin, 'payload' => $body, 'timestamp' => $timestamp])
          ->execute();
      }
    
      $response = [
        'data' => 'Success',
        'method' => 'GET'
      ];
      return new JsonResponse($response);
    }
    
    $response = [
      'data' => 'Failure',
      'method' => 'GET'
    ];
    return new JsonResponse($response);
  }
  
  /**
   * display_active_builds().
   *
   * Display a list of all the current active builds by id.
   *
   * @return array
   *   The render array for the list of active builds.
   */
  public function display_active_builds(): array {
    // Get the builds from our database.
    $query = \Drupal::database()->select('probo_builds', 'pb')
      ->fields('pb', ['id', 'bid', 'repository', 'owner', 'service', 'pull_request_name', 
        'author_name', 'pull_request_url'])
      ->condition('active', 1);
    $builds = $query->execute()->fetchAllAssoc('id');

    // Assemble the build id's into an array to be iterated through in the template.
    if (empty(count($builds))) {
      return [
        '#markup' => 'There are no active Probo builds to display.',
      ];
    }
    else {
      $config = $this->config('probo.probosettings');
      return [
        '#theme' => 'probo_build_index',
        '#probo_builds_domain' => $config->get('probo_builds_domain'),
        '#builds' => $builds,
        ''
      ];
    }
  }

  /**
   * build_details($build_id).
   * Get the details of the build including a list of all the tasks
   * associated with that build.
   *
   * @param int
   *   The build id for the build that we are displaying the details of.
   */
  public function build_details($bid): array {
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
  public function task_details($bid, $tid): array {
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
  public function service_endpoint( Request $request ): JsonResponse {
  
    // Get the input from our posted data. If no data was posted, then we can
    // bail on the operation.
    $data = json_decode($request->getContent(), FALSE);

    if (!empty($data)) {
      $build_id = $data->build_id;
      $repository = $data->repo;
      $owner = $data->owner;
      $service = $data->service;
      $pull_request_url = $data->pull_request_url;
      $pull_request_name = $data->pull_request_name;
      $author_name = $data->author_name;

      // Store our build data in the database.
      \Drupal::database()->merge('probo_builds')
        ->key(['bid' => $build_id])
        ->insertFields(['bid' => $build_id, 'owner' => $owner, 'repository' => $repository, 'service' => $service,
          'pull_request_name' => $pull_request_name, 'author_name' => $author_name, 'pull_request_url' => $pull_request_url])
        ->updateFields(['owner' => $owner, 'repository' => $repository, 'service' => $service, 'pull_request_name' => $pull_request_name, 
         'author_name' => $author_name, 'pull_request_url' => $pull_request_url])
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
}