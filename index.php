<?php
header("Content-type: application/json; charset=utf-8");

require_once 'MediweApi.php';

$response = '';
$responseCode = '';

try {
  $jsonBody = file_get_contents('php://input');
  $mediweApi = new MediweApi($_SERVER, json_decode($jsonBody), $_GET);
  if ($mediweApi->isAuthorized()) {
    // process the request
    $response = $mediweApi->processRequest();

    // return the response with "success"
    $response->is_error = 0;
    $response->error_code = 0;
    $response->error_message = '';
    $responseCode = 200;
  }
  else {
    // not authorized
    $response = new stdClass();
    $response->is_error = 1;
    $response->error_code = 401;
    $response->error_message = 'Access denied';
    $responseCode = 401;
  }
}
catch (Exception $e) {
  $response = new stdClass();
  $response->is_error = 1;
  $response->error_code = $e->getCode();
  $response->error_message = $e->getMessage();
  $responseCode = 400;
}

http_response_code($responseCode);
echo json_encode($response);
