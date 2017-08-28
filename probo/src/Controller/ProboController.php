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
    
      // Delete the record from our database if we are specifically told
      // to do so only.
      if ($data->action == 'delete') {
        \Drupal::database()->delete('probo_builds')
          ->condition('bid', $buildId)
          ->condition('tid', $taskId)
          ->execute();
      }
      // Just because we're not deleting doesn't mean we're adding. We may
      // be updating. If we have a record and a file, then do an update.
      // Otherwise, add new.
      elseif ($data->action == 'info') {
        \Drupal::database()->merge('probo_builds')
          ->key(['bid' => $buildId, 'tid' => $taskId])
          ->insertFields(['bid' => $buildId, 'tid' => $taskId, 'event_name' => $name, 'plugin' => $plugin, 'payload' => $body])
          ->updateFields(['name' => $name, 'plugin' => $plugin, 'payload' => $body])
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
      ->distinct()
      ->fields('pb', ['bid']);
    $build_objects = $query->execute()->fetchAllAssoc('bid');

    // Assemble the build id's into an array to be iterated through in the template.
    $build_id = [];
    foreach ($build_objects as $build_object) {
      $build_id[] = $build_object->bid;
    }

    if (empty(count($build_id))) {
      return [
        '#markup' => 'There are no active Probo builds to display.',
      ];
    }
    else {
      // Output.
      return [
        '#theme' => 'probo_build_index', 
        '#builds' => $build_id,
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
      ->fields('pb', ['id', 'bid', 'tid', 'event_name', 'plugin'])
      ->condition('bid', $build_details)
      ->orderBy('tid', 'ASC');
    $objects = $query->execute()->fetchAllAssoc('id');

    $tasks = [];
    foreach ($objects as $object) {
      $build_id = $object->bid;
      $tasks[] = [
        'tid' => $object->tid,
        'event_name' => $object->event_name,
        'plugin' =>$object->plugin,
      ];
    }

    // Output.
    return [
      '#theme' => 'probo_build_details', 
      '#build_id' => $build_id,
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
    $query = \Drupal::database()->select('probo_builds', 'pb')
      ->fields('pb', ['id', 'bid', 'payload', 'event_name', 'plugin'])
      ->condition('bid', $build_details)
      ->condition('tid', $task_details);
    $object = $query->execute()->fetchAssoc();

    return [
      '#theme' => 'probo_task_details',
      '#build_id' => $build_details,
      '#task_id' => $task_details,
      '#body' => $object['payload'],
      '#event_name' => $object['event_name'],
      '#plugin' => $object['plugin'],
    ];
  }
}
