<?php

$payload = file_get_contents('php://input');
$data = json_decode($payload);

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




  }
  // Just because we're not deleting doesn't mean we're adding. We may
  // be updating. If we have a record and a file, then do an update.
  // Otherwise, add new.
  elseif ($data->action == 'info') {
    file_put_contents($buildId . '-' . $taskId  . '.json', $payload);
  
  }

  $return = new stdClass();
  $return->ok = TRUE;
  print json_encode($return);
  exit();
}

$return = new stdClass();
$return->ok = FALSE;
print json_encode($return);
exit();
