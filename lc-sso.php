<?php
#  _    _          ___           __ _     (R)
# | |  (_)_ _____ / __|___ _ _  / _(_)__ _
# | |__| \ V / -_) (__/ _ \ ' \|  _| / _` |
# |____|_|\_/\___|\___\___/_||_|_| |_\__, |
#                                    |___/
# Copyright (c) 2016-2017 Keppler IT GmbH.
# ----------------------------------------------------------------------------
# LiveConfig Single Sign-on for phpMyAdmin
# Source/Documentation: https://github.com/LiveConfig/pma-sso
# License: GNU Public License (GPL) v2
# ----------------------------------------------------------------------------
# This script allows Single Sign-On from LiveConfig to phpMyAdmin without
# submitting the database credentials in clear-text through the web browser.
#
# INSTALLATION:
# 1.) copy this script (lc-sso.php) in the root directory of your phpMyAdmin
#     installation
# 2.) edit "config.inc.php" and add a new Server:
#        $i++;
#        $cfg['Servers'][$i]['auth_type'] = 'signon';
#        $cfg['Servers'][$i]['host'] = 'SingleSignOn';
#        $cfg['Servers'][$i]['SignonSession'] = 'SignonSession';
#        $cfg['Servers'][$i]['SignonURL'] = 'index.php';
#        $cfg['Servers'][$i]['LogoutURL'] = 'lc-sso.php?logout';
#     Remember the number ($i) of this new Servers entry!
# 3.) edit this script (lc-sso.php) and set PMA_SIGNON_INDEX to this Server ID
# 3a) if you change the "SignonSession" name in config.inc.php, then also
#     update PMA_SIGNON_SESSIONNAME here!
# 3b) if you use LiveConfig with a SSL certificate signed by a trusted CA, you
#     should change PMA_DISABLE_SSL_PEER_VALIDATION to FALSE
# 4.) log on to LiveConfig, go to "Servers" -> "Server Management" ->
#     "Databases". Edit your MySQL options, enable checkbox "allow single
#     sign-on".
#
# USAGE:
# Log in to LiveConfig as normal user, go to "Hosting" -> "Databases". Edit
# an existing dstabase (or create a new one) and enable Single Sign-On.
# For existing databases, you need to enter the database password (or set a
# new one), because LiveConfig doesn't save passwords unless this is 
# technically required.
# Now you see a small key icon after the phpMyAdmin link. Clicking on this
# link will redirect you to the lc-sso.php script and then immediately log
# on to phpMyAdmin.
#
#
# ----------------------------------------------------------------------------
# Workflow:
# 1.) LiveConfig redirects the user to this script. The request contains an
#     individual token.
# 2.) This script generates an AJAX request back to LiveConfig, submitting the
#     received token, a new (locally generated) token and the LiveConfig
#     session cookie.
# 3.) LiveConfig checks the token, cookie and permissions. If everything is
#     fine, it returns "success".
# 4.) On success, this script is refreshed (thus transmitting the local
#     token to PHP). The PHP part now requests the database credentials
#     from LiveConfig (authorized by the local token).
# 5.) A Single Sign-On session is generated for phpMyAdmin.
# ----------------------------------------------------------------------------

# Set this to the server configuration index from config.inc.php which
# defined the "signon" authentication method:
define("PMA_SIGNON_INDEX", 2);

# If you're running MySQL only on 127.0.0.1 (or Unix socket), un-comment
# this option. phpMyAdmin must be installed on the same server then!
# define("PMA_SIGNON_HOST", 'localhost');

# This is the name of the Single Sign-On session for phpMyAdmin. Set this
# to the same value as in config.inc.php ($cfg['Servers'][$i]['SignonSession'])
define('PMA_SIGNON_SESSIONNAME', 'SignonSession');

# Set this to TRUE to disable SSL peer validation (when you use LiveConfig
# with a self-signed certificate):
define('PMA_DISABLE_SSL_PEER_VALIDATION', TRUE);

### NO MORE CONFIGRATION OPTIONS BELOW! ###

session_name(PMA_SIGNON_SESSIONNAME);
@session_start();

if (isset($_GET['logout'])) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 86400, $params["path"], $params["domain"], $params["secure"], $params["httponly"] );
  session_destroy();
  header('Location: index.php');
  return;
}

if (isset($_POST['local_token'])) {
  $host = $_POST['lc_host'];
  $data = 'token=' . urlencode($_POST['token'])
        . '&local_token=' . urlencode($_POST['local_token'])
        . '&request=credentials';
  $result = http_query('post', $host, 'application/x-www-form-urlencoded', $data);

  if ($result['http_status']['code'] !== '200') {
    $body = "<div class=\"error\">"
          .   "<img class=\"icon ic_s_error\" src=\"themes/dot.gif\" title=\"\" alt=\"\"> "
          .   "The request to verify the token failed. Please try again!"
          . "</div>" ;
    print_page("", $body);
    return;
  }

  $json_result = json_decode($result['body'], true);
  if (!is_array($json_result) || !isset($json_result['status']) || $json_result['status'] !== true) {
    $errortxt = "Verifying the supplied token failed. Please try again!";
    if (isset($json_result['error']) && !empty($json_result['error'])) {
      $errortxt = $json_result['error'];
    }
    $body = "<div class=\"error\">"
          .   "<img class=\"icon ic_s_error\" src=\"themes/dot.gif\" title=\"\" alt=\"\"> "
          .   $errortxt
          . "</div>" ;
    print_page("", $body);
    return;
  }

  // create phpMyAdmin session from response data:
  $_SESSION['PMA_single_signon_user'] = $json_result['username'];
  $_SESSION['PMA_single_signon_password'] = $json_result['password'];
  $_SESSION['PMA_single_signon_host'] = defined("PMA_SIGNON_HOST") ? PMA_SIGNON_HOST : $json_result['hostname'];
  # $_SESSION['PMA_single_signon_port'] = '3306';
  // close that session
  @session_write_close();
  // Redirect to phpMyAdmin
  header('Location: index.php?server=' . PMA_SIGNON_INDEX);

  return;
}

if (isset($_SESSION['PMA_single_signon_error_message'])) {
  $body = "<div class=\"error\">"
        .   "<img class=\"icon ic_s_error\" src=\"themes/dot.gif\" title=\"\" alt=\"\"> "
        .   $_SESSION['PMA_single_signon_error_message']
        . "</div>" ;
  unset($_SESSION['PMA_single_signon_error_message']);
  @session_write_close();
  print_page("", $body);
  return;
}

if (!isset($_POST['token'])) {
  $body = "<div class=\"error\">"
        .   "<img class=\"icon ic_s_error\" src=\"themes/dot.gif\" title=\"\" alt=\"\"> "
        .   "No LiveConfig token specified, unable to login!"
        . "</div>" ;
  print_page("", $body);
  return;
}

if (!isset($_POST['lc_host'])) {
  $body = "<div class=\"error\">"
        .   "<img class=\"icon ic_s_error\" src=\"themes/dot.gif\" title=\"\" alt=\"\"> "
        .   "No LiveConfig hostname specified, unable to login!"
        . "</div>" ;
  print_page("", $body);
  return;
}

$head = "<script type=\"text/javascript\">"
      .   "function make_token(len) {"
      .     "var token = '';"
      .     "while(token.length < len) {"
      .       "token = token + Math.random().toString(36).substring(2);"
      .     "}"
      .     "return token.substring(0, len);"
      .   "}"
      .   "$(function() {"
      .     "var host = '". htmlspecialchars($_POST['lc_host']) ."';"
      .     "var token = '". htmlspecialchars($_POST['token']) ."';"
      .     "var local_token = make_token(128);"
      .     "$.ajax({"
      .       "accepts: 'application/json',"
      .       "cache: false,"
      .       "contentType: 'application/x-www-form-urlencoded; charset=UTF-8',"
      .       "data: {"
      .         "\"token\":token,"
      .         "\"local_token\": local_token"
      .       "},"
      .       "dataType: 'json',"
      .       "jsonp: false,"
      .       "type: 'POST',"
      .       "url: host"
      .     "})"
      .     ".done(function(data, textStatus, jqXHR) {"
      .       "if(!data.status) {"
      .         "var errortxt = 'Token verification failed. Please try again.';"
      .         "if(data.error) {"
      .           "errortxt = data.error;"
      .         "}"
      .         "$('#content_box').html("
      .           "'<img class=\"icon ic_s_error\" src=\"themes/dot.gif\" title=\"\" alt=\"\"> ' + errortxt"
      .         ");"
      .         "$('#content_box').addClass('error');"
      .         "return;"
      .       "}"
      .       "$('#content_box').append("
      .         "'<form id=\"token_form\" method=\"POST\" action=\"//". $_SERVER["SERVER_NAME"] . $_SERVER["SCRIPT_NAME"] ."\">' +"
      .           "'<input type=\"hidden\" name=\"token\" value=\"". htmlspecialchars($_POST['token']) ."\">' +"
      .           "'<input type=\"hidden\" name=\"local_token\" value=\"' + local_token +'\">' +"
      .           "'<input type=\"hidden\" name=\"lc_host\" value=\"' + host +'\">' +"
      .         "'</form>'"
      .       ");"
      .       "$('#token_form').submit();"
      .     "})"
      .     ".fail(function(jqXHR, textStatus, errorThrown) {"
      .       "var msg = 'The Ajax request to verify the token failed. (' + textStatus;"
      .       "if (errorThrown) msg += ' / ' + errorThrown;"
      .       "msg += ')';"
      .       "$('#content_box').html('<img class=\"icon ic_s_error\" src=\"themes/dot.gif\" title=\"\" alt=\"\"> ' + msg );"
      .       "$('#content_box').addClass('error');"
      .       "return;"
      .     "})"
      .     ";"
      .   "});"
      . "</script>" ;

$body = "<div id=\"content_box\" class=\"notice\"><h1>Logging in...</h1>"
      . "<p>Please wait while logging in to phpMyAdmin...</p></div>";

print_page($head, $body);

//
// Helper Functions
//
function print_page($head, $body) {
  print "<!DOCTYPE HTML>"
       ."<html>"
       .  "<head>"
       .    "<link rel=\"stylesheet\" type=\"text/css\" href=\"phpmyadmin.css.php\">"
       .    "<title>LiveConfig PHPMyAdmin Single Sign-On</title>";
  $files = glob("js/jquery/jquery-[0-9].*.min.js");
  if (!$files) $files = glob("js/jquery/jquery.min.js");
  if ($files != FALSE) print "<script src=\"" . $files[0] . "\"></script>";
  print     $head
       .  "</head>"
       .  "<body id=\"loginform\">"
       .    "<div id=\"page_content\">"
       .      "<div class=\"container\">"
       .    $body
       .      "</div>"
       .    "</div>"
       .  "</body>"
       ."</html>";
}

function http_query($method, $url, $content_type, $content, $accept_type = "application/json") {

  if (!extension_loaded("curl")) {
    $body = "<div class=\"error\">"
          .   "<img class=\"icon ic_s_error\" src=\"themes/dot.gif\" title=\"\" alt=\"\"> "
          .   "cURL extension not available."
          . "</div>" ;
    print_page("", $body);
    exit;
  }

  $myurl = $url;
  $mydata = $content;
  $header = array(
    "Content-Type: ".$content_type,
  );
  if (isset($accept_type)) {
    $header[] = "Accept: ".$accept_type."; charset=utf-8";
  }

  $http_client = curl_init($myurl);
  curl_setopt($http_client, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($http_client, CURLOPT_HEADER, true);
  curl_setopt($http_client, CURLINFO_HEADER_OUT, true);
  curl_setopt($http_client, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS | CURLPROTO_HTTP);
  curl_setopt($http_client, CURLOPT_CUSTOMREQUEST, strtoupper($method));
  curl_setopt($http_client, CURLOPT_TIMEOUT, 300);
  curl_setopt($http_client, CURLOPT_CONNECTTIMEOUT, 60);
  if (PMA_DISABLE_SSL_PEER_VALIDATION) {
    curl_setopt($http_client, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($http_client, CURLOPT_SSL_VERIFYPEER, 0);
  }
  if (isset($mydata)) {
    curl_setopt($http_client, CURLOPT_POSTFIELDS, $mydata);
  }
  curl_setopt($http_client, CURLOPT_HTTPHEADER, $header);

  $result = curl_exec($http_client);
  if($result === false) {
    return false;
  }

  $header_size = curl_getinfo($http_client, CURLINFO_HEADER_SIZE);
  $content_size = strlen($result) - $header_size;

  $header = substr($result, 0, strlen($result)-$content_size);
  $body = substr($result, strlen($result)-$content_size);

  list($header_arr, $http_status_code) = parse_headers($header);

  return array('http_status' => $http_status_code, 'header' => $header_arr, 'body' => $body);
}

function parse_headers($header) {
  $result = array();
  $lines = explode("\r\n", $header);

  $prev_key = "";
  $status_code = array();

  foreach($lines as $line) {
    if (empty($line)) continue;

    if (preg_match('/^HTTP\/\d(?:\.\d)? (\d+) (.*)$/', $line, $matches)) {
      $status_code = array('code' => $matches[1], 'text' => $matches[2]);
      continue;
    }

    list ($key, $value) = explode(":", $line, 2);

    if (!isset($value)) {
      if (substr($line, 0, 1) == "\t" || substr($line, 0, 1) == " ") {
        // Folded line
        if (!empty($prev_key)) { $result[$prev_key] .= " ".trim($line); } // add "value" to previous key value
      }
    } else {
      $key = trim($key);
      $prev_key = $key;
      $result[$key] = trim($value);
    }
  }

  return array($result, $status_code);
}

# <EOF> ----------------------------------------------------------------------
