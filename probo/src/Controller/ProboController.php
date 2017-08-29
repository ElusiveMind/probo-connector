<?php

namespace Drupal\probo\Controller;

use Drupal\Core\Controller\ControllerBase;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

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
          ->insertFields(['bid' => $buildId, 'tid' => $taskId, 'event_name' => $name, 'plugin' => $plugin, 'payload' => $body, 'timestamp' => $timestamp])
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
   */
  public function display_active_builds() {
    // Get the builds from our database.
    $query = \Drupal::database()->select('probo_builds', 'pb')
      ->fields('pb', ['id', 'bid', 'repo', 'owner', 'pr_name', 'author_name']);
    $builds = $query->execute()->fetchAllAssoc('id');

    // Assemble the build id's into an array to be iterated through in the template.
    if (empty(count($builds))) {
      return [
        '#markup' => 'There are no active Probo builds to display.',
      ];
    }
    else {
      // Output.
      return [
        '#theme' => 'probo_build_index', 
        '#builds' => $builds,
        ''
      ];
    }
  }

  /**
   * build_details($build_id).
   *
   * Get the details of the build including a list of all the tasks
   * associated with that build.
   */
  public function build_details($build_details) {
    // Get the builds from our database.
    $query = \Drupal::database()->select('probo_builds', 'pb')
      ->fields('pb', ['id', 'bid', 'repo', 'owner', 'pr_name', 'author_name'])
      ->condition('bid', $build_details);
    $build = $query->execute()->fetchAllAssoc('id');
    $build = array_pop($build);

    $build_info = [
      'bid' => $build->bid,
      'repo' => $build->repo,
      'owner' => $build->owner,
      'pr_name' => $build->pr_name,
      'author_name' => $build->author_name,
    ];

    // Get the builds from our database.
    $query = \Drupal::database()->select('probo_tasks', 'pb')
      ->fields('pb', ['id', 'bid', 'tid', 'event_name', 'plugin', 'timestamp'])
      ->condition('bid', $build_details)
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

    // Output.
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
   */
  public function task_details($build_details, $task_details) {
    // Get the builds from our database.
    $query = \Drupal::database()->select('probo_tasks', 'pb')
      ->fields('pb', ['id', 'bid', 'payload', 'event_name', 'plugin', 'timestamp'])
      ->condition('bid', $build_details)
      ->condition('tid', $task_details);
    $object = $query->execute()->fetchAssoc();

    // Get the builds from our database.
    $query = \Drupal::database()->select('probo_builds', 'pb')
      ->fields('pb', ['id', 'bid', 'repo', 'owner', 'pr_name', 'author_name'])
      ->condition('bid', $build_details);
    $build = $query->execute()->fetchAllAssoc('id');
    $build = array_pop($build);



    return [
      '#theme' => 'probo_task_details',
      '#build_id' => $build_details,
      '#task_id' => $task_details,
      '#body' => $object['payload'],
      '#event_name' => $object['event_name'],
      '#plugin' => $object['plugin'],
      '#timestamp' => $object['timestamp'],
      '#pr_name' => $build->pr_name,
      '#owner' => $build->owner,
      '#repo' => $build->repo,
    ];
  }

  /**
  * process_probo_build().
  * This is what gives the probo build its metadata that we can't get otherwise.
  */
  public function process_probo_build($build_id, $owner, $repo, $pr_name, $author_name) {
    \Drupal::database()->merge('probo_builds')
      ->key(['bid' => $build_id])
      ->insertFields(['bid' => $build_id, 'owner' => $owner, 'repo' => $repo, 'pr_name' => $pr_name, 'author_name' => $author_name])
      ->updateFields(['owner' => $owner, 'repo' => $repo, 'pr_name' => $pr_name, 'author_name' => $author_name])
      ->execute();

    header('location: http://dev.itcon-dev.com');
    exit();
  }
}