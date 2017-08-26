<?php

if ($handle = opendir('.')) {
  while (FALSE !== ($entry = readdir($handle))) {
    if ($entry != "." && $entry != "..") {
      if (substr($entry, 0, 6) == 'stream') {
        list($stream_id, $ext) = explode('.', $entry);
        $stream_id = explode('-', $stream_id);

        // Remove stream
        array_shift($stream_id);

        // Join together with the band.
        $stream_id = implode('-', $stream_id);

        // Get the information from the loom
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
        
        $json = new stdClass();
        $json->meta = $headers['x-stream-metadata'];
        $json->body = $body;
        $json_string = json_encode($json);

        // post to the service
        $ch = curl_init('https://michaelbagnall.com/l/listener.php');                                                                      
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_string);                                                                  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
            'Content-Type: application/json',                                                                                
            'Content-Length: ' . strlen($json_string))                                                                       
        );                                                                                                                   
                                                                                                                             
        $result = curl_exec($ch);
      }
    }
  }
  closedir($handle);
}
