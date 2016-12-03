<?php
require 'classes/PDO.php';

$set_bot = DB::$the->query("SELECT * FROM `sel_set_bot` ");
$set_bot = $set_bot->fetch(PDO::FETCH_ASSOC);

define('BOT_TOKEN', $set_bot['token']);
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');

echo 'key:'.$set_bot['token'].'<br>';

function apiRequestWebhook($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  $parameters["method"] = $method;

  header("Content-Type: application/json");
  echo json_encode($parameters);
  return true;
}

function exec_curl_request($handle) {
  $response = curl_exec($handle);

  if ($response === false) {
    $errno = curl_errno($handle);
    $error = curl_error($handle);
    error_log("Curl returned error $errno: $error\n");
    curl_close($handle);
    return false;
  }

  $http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
  curl_close($handle);

  if ($http_code >= 500) {
    // do not wat to DDOS server if something goes wrong
    sleep(10);
    return false;
  } else if ($http_code != 200) {
    $response = json_decode($response, true);
    error_log("Request has failed with error {$response['error_code']}: {$response['description']}\n");
    if ($http_code == 401) {
      throw new Exception('Invalid access token provided');
    }
    return false;
  } else {
    $response = json_decode($response, true);
    if (isset($response['description'])) {
      error_log("Request was successfull: {$response['description']}\n");
    }
    $response = $response['result'];
  }

  return $response;
}

function apiRequest($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  foreach ($parameters as $key => &$val) {
    // encoding to JSON array parameters, for example reply_markup
    if (!is_numeric($val) && !is_string($val)) {
      $val = json_encode($val);
    }
  }
   if(strpos($method, 'sendDocument') !== false || strpos($method, 'sendPhoto') !== false || strpos($method, 'sendVoice') !== false) $url = API_URL.$method;
   else $url = API_URL.$method.'?'.http_build_query($parameters);
  $handle = curl_init($url);
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);
  if(strpos($method, 'sendDocument') !== false || strpos($method, 'sendPhoto') !== false || strpos($method, 'sendVoice') !== false) {
	  curl_setopt($handle, CURLOPT_POST, true);
	  curl_setopt($handle, CURLOPT_POSTFIELDS, $parameters);
	  curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type:multipart/form-data"));
  }
  return exec_curl_request($handle);
}

function apiRequestJson($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  $parameters["method"] = $method;

  $handle = curl_init(API_URL);
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);
  curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
  curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
  print_r($parameters); //!!!
  return exec_curl_request($handle);
}

define('WEBHOOK_URL', trim($set_bot['url']).'/admin_modules/imbot.php');

if(isset($_GET['value']) && is_numeric($_GET['value'])){
  // if run from console, set or delete webhook
  echo 'Url:'.WEBHOOK_URL.'<br>';
  echo 'Status: '.apiRequest('setWebhook', array('url' => isset($_GET['value']) && $_GET['value'] == 0 ? '' : WEBHOOK_URL));
  exit;
} 

?>