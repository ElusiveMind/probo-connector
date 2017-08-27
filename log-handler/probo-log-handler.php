<?php

/**
 * @file probo-log-handler.php - v0.2
 * by Michael R. Bagnall <mrbagnall@icloud.com>
 *
 * Parses a directory of Probo Loom log files and sends to a service for the useful
 * purpose of debugging probo builds.
 */

// We need to provide the path to our data directory here so we can run this on
// our cron task.
$base_path = '/home/serviceadmin/probo-loom/data/';

if ($handle = opendir($base_path)) {
  while (FALSE !== ($entry = readdir($handle))) {
    if ($entry != "." && $entry != ".." && $entry != 'probo-log-handler.php') {
      if (substr($entry, 0, 6) == 'stream') {
        list($stream_id, $ext) = explode('.', $entry);
        $stream_id = explode('-', $stream_id);

        // Remove "stream".
        array_shift($stream_id);

        // Join together with the band.
        $stream_id = implode('-', $stream_id);

        // Get the build id (just the uuid).
        $build_id = explode('-', $stream_id);
        array_shift($build_id);
        array_shift($build_id);
        
        // Get the task id.
        $task_id = array_pop($build_id);
        array_pop($build_id);

        // Join together with the band.
        $build_id = implode('-', $build_id);
                
        // Get the information from the loom.
        $ch = curl_init('http://localhost:3060/stream/' . $stream_id);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        $header = substr($response, 0, $info['header_size']);
        $body = substr($response, $info['header_size']);

        foreach (explode("\r\n", $header) as $i => $line) {
          if ($i === 0) {
            $headers['http_code'] = $line;
          } 
          else {
            if (!empty($line)) {
              list($key, $value) = explode(': ', $line);
              $headers[$key] = $value;
            }
          }
        }

        // If we get an error then the stream does not exist and we can remove the file.
        // This is convenient as it automates our housekeeping :) We also need to send
        // word to our listener that they can delete their representative data from the
        // database.
        if (!empty($json->error)) {
          echo $json->error . "\n";

          // Delete the file.
          unlink($base_path . $entry);

          // Trigger removal from the remote server.
          $data = new stdClass();
          $data->action = 'delete';
          $data->buildId = $build_id;
          $data->taskId = $task_id;
          $payload = json_encode($data);
          deliver_payload($payload);
          continue;
        }

        // Assemble the payload.
        $data = (!empty($headers['x-stream-metadata'])) ? json_decode($headers['x-stream-metadata']) : new stdClass();
        $data->body = $body;
        $data->action = 'info';
        $payload = json_encode($data);
        deliver_payload($payload);

        // Uncomment only when we've gone to production.
        unlink($base_path . $entry);
      }
    }
  }
  closedir($handle);
}

/**
 * deliver_payload($payload)
 *
 * string $payload
 * The JSON string to be sent to the listener.
 */
function deliver_payload($payload) {
  // Send the payload to the service.
  $ch = curl_init('http://proofroom.net:3090/probo-api/log-listener.json');
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
  curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json',
      'Content-Length: ' . strlen($payload))
  );
  $result = curl_exec($ch);
  return $result;
}