<?php

require_once 'mediwe-api.settings.php';

class MediweApi {
  private $server;
  private $body;
  private $get;
  private $requestMethod = 'GET';

  public function __construct($server, $body, $get) {
    $this->server = $server;
    $this->body = $body;
    $this->requestMethod = $server['REQUEST_METHOD'];
  }

  public function isAuthorized() {
    // get user name and password
    $userName = array_key_exists('PHP_AUTH_USER', $this->server) ? $this->server['PHP_AUTH_USER'] : '';
    $password = array_key_exists('PHP_AUTH_PW', $this->server) ? $this->server['PHP_AUTH_PW'] : '';

    // check if they correspond with stored username and pwd
    if ($userName == USG_USER && $password == USG_PASSWORD) {
      return TRUE;
    }
    else {
      // not authorized
      return FALSE;
    }
  }

  public function processRequest() {
    $apiFunction = $this->getApiFunction();

    // the array key is what we accept, the array value is the corresponding CiviCRM entity
    // (so for only medical-inspections)
    $acceptedApiRequests = [
      'medical-inspections' => 'MedischeControle',
      'debug' => 'debug',
    ];

    if (!array_key_exists($apiFunction, $acceptedApiRequests)) {
      // not one of the expected functions
      throw new Exception('Unknown request', 400);
    }

    if ($apiFunction == 'debug') {
      // if it's debug, we just return the params in the body
      $request = new stdClass();
      $request->requestMethod = $this->requestMethod;
      $request->body = $this->getBodyAsQueryString();
      $request->get = $this->getGetAsQueryString();
    }
    else {
      // get the connection URL + the default query string (version=3, json=1...)
      $connectionURL = $this->getConnectionURL();

      // add the entity and action
      $connectionURL .= '&entity=' . $acceptedApiRequests[$apiFunction];
      $connectionURL .= '&action=' . $this->getApiAction();

      // do the curl call
      $curl = curl_init();
      if ($this->requestMethod == 'GET') {
        // add extra parameters to the url
        $paramsQueryString = $this->getGetAsQueryString();
        if ($paramsQueryString != '') {
          $connectionURL .= "&$paramsQueryString";
        }

        curl_setopt_array($curl, [
          CURLOPT_RETURNTRANSFER => 1,
          CURLOPT_URL => $connectionURL,
        ]);
      }
      else {
        $optionsArray = [
          CURLOPT_POST => 1,
          CURLOPT_RETURNTRANSFER => 1,
          CURLOPT_URL => $connectionURL,
        ];

        // add extra parameters to the array
        $paramsQueryString = $this->getBodyAsQueryString();
        if ($paramsQueryString != '') {
          $optionsArray[CURLOPT_POSTFIELDS] = $paramsQueryString;
        }

        curl_setopt_array($curl, $optionsArray);
      }
      $request = new stdClass();
      $response = curl_exec($curl);

      // handle errors
      if ($response === FALSE) {
        throw new Exception(curl_error($curl), curl_errno($curl));
      }

      // decode the result and check for errors
      $request->response = json_decode($response);
      if (property_exists($request->response, 'is_error') && $request->response->is_error == 1) {
        throw new Exception($request->response->error_message, 1);
      }

      // remove some properties
      unset($request->response->is_error);
      unset($request->response->version);

      // no error, close
      curl_close($curl);
    }

    return $request;
  }

  private function getConnectionURL() {
    $url = CIVI_URL . '?json=1&version=3&api_key=' . API_KEY . '&key=' . SITE_KEY;
    return $url;
  }

  private function getApiFunction() {
    $apiFunction = '';
    $delimiter = '/mediwe-api/';

    if (array_key_exists('REDIRECT_URL', $this->server)) {
      $n = strpos($this->server['REDIRECT_URL'], $delimiter);
      if ($n !== FALSE) {
        $apiFunction = substr($this->server['REDIRECT_URL'], $n + strlen($delimiter));
      }
    }

    return $apiFunction;
  }

  private function getApiAction() {
    // convert the http request method to a CiviCRM api action
    if ($this->requestMethod == 'PUT' || $this->requestMethod == 'POST') {
      return 'create';
    }
    else {
      return 'get';
    }
  }

  private function getBodyAsQueryString() {
    $queryString = '';
    $vars = get_object_vars($this->body);
    foreach ($vars as $k => $v) {
      if ($queryString == '') {
        $queryString = "$k=$v";
      }
      else {
        $queryString .= "&$k=$v";
      }
    }

    return $queryString;
  }

  private function getGetAsQueryString() {
    $queryString = '';
    foreach ($this->get as $k => $v) {
      if ($queryString == '') {
        $queryString = "$k=$v";
      }
      else {
        $queryString .= "&$k=$v";
      }
    }

    return $queryString;
  }

}