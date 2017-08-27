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
}
